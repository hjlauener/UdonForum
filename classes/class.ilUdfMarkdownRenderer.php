<?php

declare(strict_types=1);

use ILIAS\HTTP\GlobalHttpState;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\UI\Component\Input\Field\MarkdownRenderer;

/**
 * Markdown for the Udon Forum: preview of the Markdown input field (ILIAS UI)
 * and conversion of stored posts (Markdown or plain text) to HTML.
 *
 * The conversion uses the ILIAS Refinery (CommonMark): raw HTML in the text
 * is escaped, unsafe links (e.g. javascript:) are not allowed, single line
 * breaks are kept. Web addresses in the text are made clickable.
 */
class ilUdfMarkdownRenderer implements MarkdownRenderer
{
    public const PARAM_PREVIEW = 'udf_md_preview';

    public function __construct(
        private string $async_url,
        private Refinery $refinery,
        private GlobalHttpState $http
    ) {
    }

    public function getAsyncUrl(): string
    {
        return $this->async_url;
    }

    public function getParameterName(): string
    {
        return self::PARAM_PREVIEW;
    }

    /**
     * Sends the preview of the submitted text (the Markdown field inserts the
     * response text as HTML).
     */
    public function renderAsync(): void
    {
        $text = '';
        $post = $this->http->wrapper()->post();
        if ($post->has(self::PARAM_PREVIEW)) {
            $text = $post->retrieve(self::PARAM_PREVIEW, $this->refinery->kindlyTo()->string());
        }

        $this->http->saveResponse(
            $this->http->response()
                ->withHeader('Content-Type', 'text/html; charset=utf-8')
                ->withBody(\ILIAS\Filesystem\Stream\Streams::ofString($this->render($text)))
        );
        $this->http->sendResponse();
        $this->http->close();
    }

    public function render(string $markdown_text): string
    {
        if (trim($markdown_text) === '') {
            return '';
        }
        $html = $this->refinery->string()->markdown()->toHTML()->transform($markdown_text);
        return $this->makeClickable($html);
    }

    /**
     * Plain text (written without Markdown editor): escaped, line breaks kept,
     * web addresses clickable.
     */
    public function renderPlain(string $text): string
    {
        if (trim($text) === '') {
            return '';
        }
        return '<p>' . nl2br($this->makeClickable(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'))) . '</p>';
    }

    private function makeClickable(string $html): string
    {
        $html = $this->refinery->string()->makeClickable()->transform($html);

        // punctuation at the end of a web address belongs to the sentence, not to the link
        return (string) preg_replace(
            '~<a target="_blank" rel="noopener" href="([^"]+?)([.,;:!?)]+)">\\1\\2</a>~u',
            '<a target="_blank" rel="noopener" href="$1">$1</a>$2',
            $html
        );
    }

    /**
     * Plain text of a Markdown text (for the preview in the feed).
     */
    public function toPlainText(string $markdown_text): string
    {
        if (trim($markdown_text) === '') {
            return '';
        }
        $html = $this->refinery->string()->markdown()->toHTML()->transform($markdown_text);
        $text = html_entity_decode(strip_tags(str_replace(['<br/>', '<br />', '</p>', '</li>'], ' ', $html)), ENT_QUOTES, 'UTF-8');
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
