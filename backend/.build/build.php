<?php

namespace Build;

use RuntimeException;
use SplFileObject;

class BuildEnum
{
    public const INTERN = 'Intern';
    public const JUNIOR = 'Junior';
    public const MIDDLE = 'Middle';
    public const SENIOR = 'Senior';

    public const GRADES = [
        ['I1', 'Intern Start', self::INTERN],
        ['J1', 'Junior Start', self::JUNIOR],
        ['J2', 'Junior Full', self::JUNIOR],
        ['M1', 'Middle Start', self::MIDDLE],
        ['M2', 'Middle Progressive', self::MIDDLE],
        ['M3', 'Middle Full', self::MIDDLE],
        ['S1', 'Senior Start', self::SENIOR],
        ['S2', 'Senior Full', self::SENIOR],
    ];

    public const CATEGORIES = [
        'php',
        'db',
        'frontend',
        'test',
        'cse',
        'git',
        'devops',
        'security',
        'internal-infra',
    ];
}

class Dir
{
    public static string $main;
    public static string $categories;
    public static string $build;
}

Dir::$main = dirname(__DIR__) . '/main';
Dir::$categories = dirname(__DIR__) . '/category';
Dir::$build = dirname(__DIR__);

class Grade
{
    public string $group;
    public string $title;
    public string $gradeIndexU;
    public string $gradeIndexL;
    public int $index;
    public string $filename;

    public ?string $main = null;
    public array $data = [];

    public function __construct(string $group, string $title, string $gradeIndex, int $index)
    {
        $this->group = $group;
        $this->title = $title;
        $this->gradeIndexU = mb_strtoupper($gradeIndex);
        $this->gradeIndexL = mb_strtolower($gradeIndex);
        $this->index = $index;
    }

    public function readMain(): void
    {
        $mainDir = Dir::$main;
        $filename = "{$mainDir}/{$this->gradeIndexL}.md";
        if (!file_exists($filename)) {
            return;
        }
        $main = trim(file_get_contents($filename));
        if ($main === '') {
            return;
        }
        $this->main = $main;
    }

    public function buildData(array $categories): void
    {
        /** @var Category $category */
        foreach ($categories as $category) {
            if (!isset($category->data[$this->gradeIndexU])) {
                continue;
            }
            $this->data[] = [
                'title' => $category->title,
                'data' => $category->data[$this->gradeIndexU],
            ];
        }
    }

    public function writeFile()
    {
        $buildDir = Dir::$build;
        $title = preg_replace('~\s+~', ' ', $this->title);
        $title = mb_strtolower($title);
        $title = str_replace(' ', '-', $title);
        $this->filename = "p{$this->index}-{$this->gradeIndexL}-{$title}.md";

        $fo = new SplFileObject("{$buildDir}/{$this->filename}", 'w');
        $fo->fwrite("# {$this->title} ({$this->gradeIndexU}, P{$this->index})\n");
        if ($this->main !== null) {
            $fo->fwrite("\n");
            $fo->fwrite($this->main . "\n");
        }
        if ($this->data !== []) {
            foreach ($this->data as $categoryData) {
                $fo->fwrite("\n## {$categoryData['title']}\n\n");
                foreach ($categoryData['data'] as $line) {
                    $fo->fwrite($line . "\n");
                }
            }
        }
    }
}

class Category
{
    public ?string $title = null;
    public array $data = [];
}

class CategoryParser
{
    private string $filename;
    private ?SplFileObject $fo;

    public function __construct(string $category)
    {
        $categoriesDir = Dir::$categories;
        $filename = "{$categoriesDir}/{$category}.md";
        if (!file_exists($filename)) {
            throw new RuntimeException("Category not found {$filename}.");
        }
        $this->filename = $filename;
    }

    public function readFile(): Category
    {
        $this->fo = new SplFileObject($this->filename);
        $this->fo->setFlags(SplFileObject::DROP_NEW_LINE);

        $category = new Category();

        $empty = false;
        $grade = null;

        while (!$this->fo->eof()) {
            $line = $this->fo->fgets();
            $tline = trim($line);

            if ($tline === '') {
                if ($grade === null) {
                    continue;
                }
                if ($empty) {
                    continue;
                }
                $empty = true;
            } else {
                $empty = false;
            }

            if (strpos($tline, '#') === 0) {
                $heading = strpos($tline, ' ');
                $head = trim(mb_substr($tline, $heading + 1));

                if ($heading === 1 && $category->title === null) {
                    $category->title = $head;
                    continue;
                }

                if ($heading === 3) {
                    $grade = mb_strtoupper($head);
                    if (!isset($this->category->data[$grade])) {
                        $category->data[$grade] = [];
                    }
                    continue;
                }
            }

            $category->data[$grade][] = $line;
        }

        $this->trimLines($category);

        return $category;
    }

    private function trimLines(Category $category): void
    {
        foreach ($category->data as &$grade) {
            if ($grade === []) {
                continue;
            }
            while (reset($grade) === '') {
                array_shift($grade);
            }
            while (end($grade) === '') {
                array_pop($grade);
            }
        }
        $category->data = array_filter($category->data);
    }
}

$categories = [];
foreach (BuildEnum::CATEGORIES as $category) {
    $parser = new CategoryParser($category);
    $categories[$category] = $parser->readFile();
}

$buildDir = Dir::$build;
foreach (glob("{$buildDir}/*.md") as $file) {
    unlink($file);
}

$fo = new SplFileObject("{$buildDir}/README.md", 'w');
$fo->fwrite("# Backend Grades\n\n");
$fo->fwrite("1) Сначала правим требования по категориям в backend/category\n");
$fo->fwrite("2) Запускаем скрипт сборки грейдов `php backend/.build/build.php`\n");
$fo->fwrite("3) Коммитим, пушим и правки по категории, и по грейду\n");

$index = 0;
$group = null;
foreach (BuildEnum::GRADES as $grade) {
    $grade = new Grade($grade[2], $grade[1], $grade[0], $index);
    $grade->readMain();
    $grade->buildData($categories);
    $grade->writeFile();

    if ($group !== $grade->group) {
        $fo->fwrite("\n## {$grade->group}\n\n");
        $group = $grade->group;
    }
    $fo->fwrite("* P{$index} {$grade->gradeIndexU} [{$grade->title}]({$grade->filename})\n");

    $index++;
}
