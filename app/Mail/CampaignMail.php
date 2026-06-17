<?php

namespace App\Mail;

use App\Models\Campaign;
use App\Models\Contact;
use App\Support\MergeTagReplacer;
use App\Support\TracksEmailContent;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

class CampaignMail extends Mailable
{
    use Queueable, SerializesModels, TracksEmailContent;

    public function __construct(
        public Campaign $campaign,
        public Contact $contact,
        public int $queueId,
        public ?string $abVariant = null,
        public string $bodyHtml = '',
        public string $subjectLine = '',
    ) {
    }

    private function effectiveSubject(): string
    {
        // Prefer the pre-captured subject from the queue item (already A/B selected).
        if ($this->subjectLine !== '') {
            return $this->subjectLine;
        }

        if ($this->abVariant === 'b' && !empty($this->campaign->ab_subject_b)) {
            return (string) $this->campaign->ab_subject_b;
        }
        return (string) $this->campaign->subject;
    }

    private function effectiveBody(): string
    {
        // Prefer the pre-captured body from the queue item (already A/B selected).
        // This is more reliable than re-reading from $campaign->body which may differ
        // from what was captured at queue time, or may be empty/null in edge cases.
        if ($this->bodyHtml !== '') {
            return $this->bodyHtml;
        }

        if ($this->abVariant === 'b' && !empty($this->campaign->ab_body_b)) {
            return (string) $this->campaign->ab_body_b;
        }
        return (string) $this->campaign->body;
    }

    public function build(): static
    {
        $unsubscribeUrl = route('unsubscribe', ['email' => rawurlencode($this->contact->email)]);

        $renderedSubject = $this->replaceMergeTags($this->effectiveSubject(), $this->contact);

        $rawBody      = $this->effectiveBody();
        $body         = $this->replaceMergeTags($rawBody, $this->contact);

        $readyHtml    = $this->prepareHtmlForEmail($body);
        $trackedHtml  = $this->buildTrackedHtml($readyHtml, $this->queueId, true, $this->contact->email);

        $this->withSymfonyMessage(function (\Symfony\Component\Mime\Email $email) use ($unsubscribeUrl) {
            $email->getHeaders()
                ->addTextHeader('List-Unsubscribe', '<' . $unsubscribeUrl . '>');
            // NOTE: List-Unsubscribe-Post (One-Click) header intentionally removed.
            // It causes Gmail/Outlook bots to auto-unsubscribe users without any
            // real click — inflating unsubscribe counts with false positives.
        });

        if (!empty($this->campaign->attachment_path) && Storage::disk('public')->exists($this->campaign->attachment_path)) {
            $this->attach(
                Storage::disk('public')->path($this->campaign->attachment_path),
                ['as' => $this->campaign->attachment_name ?: basename($this->campaign->attachment_path)]
            );
        }

        return $this->subject($renderedSubject)->html($trackedHtml);
    }

    private function replaceMergeTags(string $body, Contact $contact): string
    {
        return MergeTagReplacer::replace($body, $contact);
    }

    /**
     * Prepare HTML for email delivery.
     *
     * Strategy (same pipeline for both full documents and fragments):
     * 1. Strip unsafe elements.
     * 2. Resolve CSS custom properties (var(--x) → actual values) so the inliner
     *    sees real color/size values instead of unresolvable var() references.
     * 3. Wrap plain fragments in a minimal email-safe document.
     * 4. Inline <style> rules into element style="" attributes so styles survive
     *    Gmail stripping the <style> block.
     */
    private function prepareHtmlForEmail(string $html): string
    {
        $trimmed = trim($html);

        if ($trimmed === '') {
            return '';
        }

        $isFullDocument = stripos($trimmed, '<html') !== false;

        // Step 1: Strip unsafe blocks.
        $safe = $this->stripUnsafeHtml($trimmed);

        // Step 1b: Remove CSS animations & transitions before inlining.
        // Email clients strip the <style> block, so any element whose initial
        // state is opacity:0 (waiting for a CSS animation to reveal it) will
        // stay permanently invisible.  We remove @keyframes, flip opacity:0
        // to opacity:1 in animated rules, and drop all animation/transition
        // declarations so the inliner never writes them into style="" attrs.
        $safe = $this->stripAnimationsFromStyles($safe);

        // Step 2: Resolve CSS custom properties BEFORE inlining.
        $resolved = $this->resolveCssVariables($safe);

        // Step 3: Wrap plain fragments.
        if (!$isFullDocument) {
            $resolved = '<!doctype html><html><head>'
                . '<meta charset="UTF-8">'
                . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
                . '</head>'
                . '<body style="margin:0;padding:20px 16px 60px;background:#ffffff;color:#1a1a1a;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;">'
                . '<div style="max-width:600px;margin:0 auto;">' . $resolved . '</div>'
                . '</body></html>';
        }

        // Step 4: Inline CSS.
        try {
            $inliner = new CssToInlineStyles();
            $inlined = $inliner->convert($resolved);

            if (trim($inlined) === '') {
                // Inliner returned nothing — send the variable-resolved HTML as fallback.
                return $resolved;
            }

            return $inlined;
        } catch (\Throwable $e) {
            Log::error('CampaignMail CSS inline exception', ['error' => $e->getMessage()]);
            return $resolved;
        }
    }

    private function stripUnsafeHtml(string $html): string
    {
        // Remove scripts, iframes, and external stylesheet links.
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $html) ?? $html;
        $html = preg_replace('/<link\b[^>]*rel=["\']stylesheet["\'][^>]*>/is', '', $html) ?? $html;
        // Remove inline event handlers.
        $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        return $html;
    }

    /**
     * Strip CSS animations and transitions from <style> blocks before inlining.
     *
     * Email clients (Gmail, Outlook, Apple Mail) strip the <style> block after
     * delivery, so:
     *   - @keyframes blocks are useless and must be removed.
     *   - Elements that start with opacity:0 and rely on a CSS animation to
     *     become visible will stay permanently invisible → blank email.
     *
     * This method:
     *   1. Removes every @keyframes block (including vendor-prefixed variants).
     *   2. For any CSS rule that contains an `animation` property, replaces
     *      opacity:0 with opacity:1 so animation-reveal elements stay visible.
     *   3. Removes all animation and transition declarations from every rule.
     */
    private function stripAnimationsFromStyles(string $html): string
    {
        return preg_replace_callback('/<style([^>]*)>(.*?)<\/style>/is', function (array $m): string {
            $attrs = $m[1];
            $css   = $m[2];

            // 1. Remove @keyframes blocks (handles one level of nested braces
            //    for the from/to / percentage keyframe selectors).
            $css = preg_replace(
                '/@(?:-webkit-|-moz-|-o-)?keyframes\s+[\w-]+\s*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/is',
                '',
                $css
            ) ?? $css;

            // 2 & 3. Walk each individual CSS rule block.
            $css = preg_replace_callback('/([^{}]+)\{([^{}]*)\}/s', function (array $rm): string {
                $selector = $rm[1];
                $body     = $rm[2];

                // If the rule drives a CSS animation, its opacity:0 is only an
                // animation-reveal initial state — flip it so the element is visible.
                if (preg_match('/\banimation\s*:/i', $body)) {
                    $body = preg_replace('/\bopacity\s*:\s*0\b\s*;?/i', 'opacity:1;', $body) ?? $body;
                }

                // Remove animation and transition shorthand / longhand properties.
                $body = preg_replace('/\banimation(-[a-z-]+)?\s*:[^;]+;?/i', '', $body) ?? $body;
                $body = preg_replace('/\btransition(-[a-z-]+)?\s*:[^;]+;?/i', '', $body) ?? $body;

                return $selector . '{' . $body . '}';
            }, $css) ?? $css;

            return '<style' . $attrs . '>' . $css . '</style>';
        }, $html) ?? $html;
    }

    /**
     * Resolve CSS custom properties before CSS inlining.
     * Reads --var-name definitions from :root / html blocks inside <style> tags
     * and replaces every var(--x) in the document with the real value.
     */
    private function resolveCssVariables(string $html): string
    {
        $vars = [];

        preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $html, $styleMatches);
        foreach ($styleMatches[1] as $styleContent) {
            if (preg_match_all('/:root\s*\{([^}]+)\}|html\s*\{([^}]+)\}/i', $styleContent, $rootMatches)) {
                foreach ($rootMatches[0] as $block) {
                    preg_match_all('/(--[\w-]+)\s*:\s*([^;}\n]+)/i', $block, $varMatches, PREG_SET_ORDER);
                    foreach ($varMatches as $m) {
                        $vars[trim($m[1])] = trim($m[2]);
                    }
                }
            }
        }

        // Also check inline style on <html> element.
        if (preg_match('/<html[^>]+style="([^"]+)"/i', $html, $htmlStyleMatch)) {
            preg_match_all('/(--[\w-]+)\s*:\s*([^;,"]+)/i', $htmlStyleMatch[1], $varMatches, PREG_SET_ORDER);
            foreach ($varMatches as $m) {
                $vars[trim($m[1])] = trim($m[2]);
            }
        }

        if (empty($vars)) {
            return $html;
        }

        return preg_replace_callback('/var\((--[\w-]+)(?:\s*,\s*([^)]+))?\)/i', function (array $m) use ($vars): string {
            return $vars[$m[1]] ?? (isset($m[2]) ? trim($m[2]) : 'initial');
        }, $html) ?? $html;
    }
}
