<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\HtmlMinify {
    use PHPCraftdream\Garnet\Kernel\Interfaces\IHtmlMinify;

    class HtmlMinify implements IHtmlMinify {
        protected array $options;

        protected string $output;

        protected array $build;

        protected int $skip;

        protected string $skipName;

        protected bool $head;

        protected array $elements;

        /**
         * @param array $options
         */
        public function __construct(array $options) {
            $this->options = $options;
            $this->output = '';
            $this->build = [];
            $this->skip = 0;
            $this->skipName = '';
            $this->head = false;
            $this->elements = [
                'skip' => [
                    'code',
                    'pre',
                    'script',
                    'textarea',
                ],
                'inline' => [
                    'a',
                    'abbr',
                    'acronym',
                    'b',
                    'bdo',
                    'big',
                    'br',
                    'cite',
                    'code',
                    'dfn',
                    'em',
                    'i',
                    'img',
                    'kbd',
                    'map',
                    'object',
                    'samp',
                    'small',
                    'span',
                    'strong',
                    'sub',
                    'sup',
                    'tt',
                    'var',
                    'q',
                ],
                'hard' => [
                    '!doctype',
                    'body',
                    'html',
                ]
            ];
        }

        protected static IHtmlMinify $instance;

        public static function get(): IHtmlMinify {
            if (empty(static::$instance)) {
                static::$instance = new static(['collapse_whitespace' => true]);
            }

            return static::$instance;
        }

        /**
         * @param string $html
         * @return string
         */
        public function minify(string $html): string {
            $rest = $html;

            while (!empty($rest)) {
                $parts = explode('<', $rest, 2);
                $this->walk($parts[0]);
                $rest = (isset($parts[1])) ? $parts[1] : '';
            }

            return $this->output;
        }

        /**
         * @param string $part
         * @return void
         */
        protected function walk(string $part): void {
            $tag_parts = explode('>', $part);
            $tag_content = $tag_parts[0];

            if (!empty($tag_content)) {
                $name = $this->findName($tag_content);
                $element = $this->toElement($tag_content, $part);
                $type = $this->toType($element);

                if ($name === 'head') {
                    $this->head = $type === 'open';
                }

                $this->build[] = [
                    'name' => $name,
                    'content' => $element,
                    'type' => $type
                ];

                $this->setSkip($name, $type);

                $content = (isset($tag_parts[1])) ? $tag_parts[1] : '';

                if ($content !== '') {
                    $this->build[] = [
                        'content' => $this->compact($content, $name),
                        'type' => 'content'
                    ];
                }

                $this->buildHtml();
            }
        }

        /**
         * @param string $element
         * @return string
         */
        protected function toType(string $element): string {
            return (substr($element, 1, 1) === '/') ? 'close' : 'open';
        }

        /**
         * @param string $element
         * @param string $noll
         * @return string
         */
        protected function toElement(string $element, string $noll): string {
            $element = $this->stripWhitespace($element);

            return $this->addChevrons($element, $noll);
        }

        /**
         * @param string $element
         * @return string
         */
        protected function stripWhitespace(string $element): string {
            if ($this->skip === 0) {
                $element = preg_replace('/\s+/', ' ', $element) . '';
            }

            return trim($element);
        }

        /**
         * @param string $element
         * @param string $noll
         * @return string
         */
        protected function addChevrons(string $element, string $noll): string {
            if (empty($element)) {
                return $element;
            }

            $char = str_contains($noll, '>') ? '>' : '';

            return '<' . $element . $char;
        }

        /**
         * @param string $content
         * @param string $name
         * @return string|array|string[]|null
         */
        protected function compact(string $content, string $name): string|array|null {
            if ($this->skip !== 0) {
                $name = $this->skipName;
            } else {
                $content = preg_replace('/\s+/', ' ', $content) . '';
            }

            if (in_array($name, $this->elements['skip'], true)) {
                return $content;
            } elseif (in_array($name, $this->elements['hard'], true) ||
                $this->head) {
                return $this->minifyHard($content);
            }

            return $this->minifyKeepSpaces($content);
        }

        /**
         * @return void
         */
        protected function buildHtml(): void {
            foreach ($this->build as $build) {
                if (!empty($this->options['collapse_whitespace'])) {
                    if (trim($build['content']) === '') {
                        continue;
                    } elseif ($build['type'] !== 'content' && !in_array($build['name'], $this->elements['inline'], true)) {
                        $build['content'] = trim($build['content']);
                    }
                }

                $this->output .= $build['content'];
            }

            $this->build = [];
        }

        /**
         * @param string $part
         * @return string
         */
        protected function findName(string $part): string {
            $name_cut = explode(' ', $part, 2)[0];
            $name_cut = explode('>', $name_cut, 2)[0];
            $name_cut = explode("\n", $name_cut, 2)[0];
            $name_cut = preg_replace('/\s+/', '', $name_cut) . '';

            return strtolower(str_replace('/', '', $name_cut));
        }

        /**
         * @param string $name
         * @param string $type
         * @return void
         */
        protected function setSkip(string $name, string $type): void {
            foreach ($this->elements['skip'] as $element) {
                if ($element === $name && $this->skip === 0) {
                    $this->skipName = $name;
                }
            }

            if (in_array($name, $this->elements['skip'], true)) {
                if ($type === 'open') {
                    $this->skip++;
                }

                if ($type === 'close') {
                    $this->skip--;
                }
            }
        }

        /**
         * @param string $element
         * @return string
         */
        protected function minifyHard(string $element): string {
            $element = preg_replace('!\s+!', ' ', $element) . '';
            $element = trim($element);

            return trim($element);
        }

        /**
         * @param string $element
         * @return array|string|null
         */
        protected function minifyKeepSpaces(string $element): array|string|null {
            return preg_replace('!\s+!', ' ', $element);
        }
    }
}
