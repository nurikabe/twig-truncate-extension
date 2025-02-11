<?php

namespace Bluetel\Twig;

use DOMElement;
use DOMNode;
use DOMText;
use DOMDocument;
use DOMWordsIterator;
use DOMLettersIterator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * TruncateExtension
 * @author Alex Wilson <ajw@bluetel.co.uk>
 * @license MIT
 */
class TruncateExtension extends AbstractExtension
{
    /**
     * @return array<string, TwigFilter> Returns the list of filters supplied by this extension.
     */
    public function getFilters(): array
    {
        $truncateWords = new TwigFilter(
            'truncate_words',
            [$this, 'truncateWords'],
            [
                'is_safe' => ['html'],
            ]
        );

        $truncateLetters = new TwigFilter(
            'truncate_letters',
            [$this, 'truncateLetters'],
            [
                'is_safe' => ['html'],
            ]
        );

        return [
            'truncate_letters' => $truncateWords,
            'truncate_words'   => $truncateLetters,
        ];
    }

    /**
     * Safely truncates HTML by a given number of words.
     * @param  string  $html     Input HTML.
     * @param  integer $limit    Limit to how many words we preserve.
     * @param  string  $ellipsis String to use as ellipsis (if any).
     * @return string            Safe truncated HTML.
     */
    public function truncateWords(string $html, int $limit = 0, string $ellipsis = ''): string
    {
        if ($limit <= 0) {
            return $html;
        }

        $dom = $this->htmlToDomDocument($html);

        // Grab the body of our DOM.
        $body = $dom->getElementsByTagName('body')->item(0);

        // Iterate over words.
        $words = new DOMWordsIterator($body);
        foreach ($words as $word) {

            // If we have exceeded the limit, we delete the remainder of the content.
            if ($words->key() >= $limit) {

                // Grab current position.
                $currentWordPosition = $words->currentWordPosition();
                $curNode = $currentWordPosition[0];
                $offset = $currentWordPosition[1];
                $words = $currentWordPosition[2];

                $curNode->nodeValue = substr(
                    $curNode->nodeValue,
                    0,
                    $words[$offset][1] + \strlen($words[$offset][0])
                );

                self::removeProceedingNodes($curNode, $body);

                if (!empty($ellipsis)) {
                    self::insertEllipsis($curNode, $ellipsis);
                }

                break;
            }

        }

        return (\is_string($dom->saveHTML())) ? $dom->saveHTML() : '';
    }

    /**
     * Safely truncates HTML by a given number of letters.
     * @param  string  $html     Input HTML.
     * @param  integer $limit    Limit to how many letters we preserve.
     * @param  string  $ellipsis String to use as ellipsis (if any).
     * @return string            Safe truncated HTML.
     */
    public function truncateLetters(string $html, int $limit = 0, string $ellipsis = ''): string
    {
        if ($limit <= 0) {
            return $html;
        }

        $dom = $this->htmlToDomDocument($html);

        // Grab the body of our DOM.
        $body = $dom->getElementsByTagName('body')->item(0);

        // Iterate over letters.
        $letters = new DOMLettersIterator($body);
        foreach ($letters as $letter) {

            // If we have exceeded the limit, we want to delete the remainder of this document.
            if ($letters->key() >= $limit) {

                $currentText = $letters->currentTextPosition();
                $currentText[0]->nodeValue = substr($currentText[0]->nodeValue, 0, $currentText[1] + 1);
                self::removeProceedingNodes($currentText[0], $body);

                if (!empty($ellipsis)) {
                    self::insertEllipsis($currentText[0], $ellipsis);
                }

                break;
            }
        }

        return (\is_string($dom->saveHTML())) ? $dom->saveHTML() : '';
    }

    /**
     * Builds a DOMDocument object from a string containing HTML.
     * @param string $html HTML to load
     * @returns DOMDocument Returns a DOMDocument object.
     */
    public function htmlToDomDocument(string $html): DOMDocument
    {
        // Transform multibyte entities which otherwise display incorrectly.
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');

        // Internal errors enabled as HTML5 not fully supported.
        libxml_use_internal_errors(true);

        // Instantiate new DOMDocument object, and then load in UTF-8 HTML.
        $dom = new DOMDocument();
        $dom->encoding = 'UTF-8';
        $dom->loadHTML($html);

        return $dom;
    }

    /**
     * Removes all nodes after the current node.
     * @return void
     */
    private static function removeProceedingNodes(DOMNode $domNode, ?DOMNode $topNode): void
    {
        $nextNode = $domNode->nextSibling;

        if ($nextNode !== null) {
            self::removeProceedingNodes($nextNode, $topNode);
            $domNode->parentNode?->removeChild($nextNode);
        } else {
            //scan upwards till we find a sibling
            $curNode = $domNode->parentNode;
            while ($curNode !== $topNode && $topNode !== null) {
                if ($curNode->nextSibling !== null) {
                    $curNode = $curNode->nextSibling;
                    self::removeProceedingNodes($curNode, $topNode);
                    $curNode?->parentNode->removeChild($curNode);
                    break;
                }
                $curNode = $curNode->parentNode;
            }
        }
    }

    /**
     * Inserts an ellipsis
     * @param DOMNode $domNode  Element to insert after.
     * @param string $ellipsis Text used to suffix our document.
     * @return void
     */
    private static function insertEllipsis(DOMNode $domNode, string $ellipsis): void
    {
        $avoid = ['a', 'strong', 'em', 'h1', 'h2', 'h3', 'h4', 'h5']; //html tags to avoid appending the ellipsis to

        if ($domNode->parentNode->parentNode !== null && in_array($domNode->parentNode->nodeName, $avoid)) {
            // Append as text node to parent instead
            $textNode = new DOMText($ellipsis);

            if ($domNode->parentNode->parentNode->nextSibling) {
                $domNode->parentNode->parentNode->insertBefore($textNode, $domNode->parentNode->parentNode->nextSibling);
            } else {
                $domNode->parentNode->parentNode->appendChild($textNode);
            }

        } else {
            // Append to current node
            $domNode->nodeValue = rtrim($domNode->nodeValue ?? '') . $ellipsis;
        }
    }

    /**
     * Returns the name of this extension.
     * @return string
     */
    public function getName(): string
    {
        return 'truncate_extension';
    }
}
