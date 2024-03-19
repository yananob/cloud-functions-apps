<?php declare(strict_types=1);

namespace MyApp;

class OmlBook
{
    public string $fullTitle;
    public string $title;
    public string $author;
    public string $publishedYear;

    public function __construct(string $fullTitle)
    {
        $this->fullTitle = $fullTitle;

        $this->title = $this->__getTitlePart(0);
        $this->author = $this->__getTitlePart(1);
        $this->publishedYear = $this->__getTitlePart(3);
    }

    private function __getTitlePart(int $index): string
    {
        $titles = explode("∥", $this->fullTitle);
        if (count($titles) > $index) {
            return $titles[$index];
        }
        else {
            return "";
        }
    }
}
