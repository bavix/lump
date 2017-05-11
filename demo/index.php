<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

class Simple
{
    public $title   = 'Hello World';
    public $welcome = 'Hi, Lump!';

    public $aaa = 'aaa';
    public $html = '<h3>HTML</h3>';

    public $foo = [
        'bar' => [
            'baz' => 'qux'
        ]
    ];

    public $ignore = '111';

    public $links = [
        ['href' => 'bavix.ru'],
        ['href' => 'bavix.ru', 'title' => 'baVix'],
        ['href' => 'https://github.com/bavix', 'title' => 'GitHub!'],
    ];
}

$template = <<<lump
{{%FILTERS}}{{% ANCHORED-DOT}}<!DOCTYPE html>
<html>
    <head><title>{{ #title }}{{title}}{{^title}}No title{{/title}}</title></head>
    <body>
        <h1>{{ welcome }} {{#foo}}{{#bar}}{{baz}}{{/bar}}{{/foo}} {{foo.bar.baz}} {{^aaa}}asdfdasf{{/aaa}}</h1>
        {{{html}}}
        <h3>links: {{ links | count }}</h3>
        <ul>
          {{# links }}
            <li>
              <a
                title="{{ .title }}{{^ .title }}{{ href }}{{/ .title }}" 
                href="{{ href }}">{{ .title }}{{^ .title }}{{ href }}{{/ .title }}</a>
            </li>
          {{/ links }}
        </ul>    
    </body>
</html>
lump;

$fileSystem = new \Bavix\Lump\Caches\FilesystemCache(__DIR__ . '/cache');

$lump = new Bavix\Lump\Lump();
$lump->setCache($fileSystem);

$lump->addHelper('dump', function ($value) {
    var_dump($value);
    return null;
});

$lump->addHelper('count', function ($value) {
    return count($value);
});

$lump->addHelper('empty', function ($value) {
    return empty($value);
});

echo $lump->render($template, new Simple());
