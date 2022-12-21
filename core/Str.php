<?php

namespace MkyCore;

use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;

class Str
{
    public static function camelize(string $string): string
    {
        return self::inflection()->camelize($string);
    }

    private static function inflection(): Inflector
    {
        return InflectorFactory::create()->build();
    }

    public static function tableize(string $string): string
    {
        return self::inflection()->tableize($string);
    }

    public static function capitalize(string $string, string $delimiters = " \n\t\r\0\x0B-"): string
    {
        return self::inflection()->capitalize($string, $delimiters);
    }

    public static function classify(string $string): string
    {
        return self::inflection()->classify($string);
    }

    public static function pluralize(string $string): string
    {
        return self::inflection()->pluralize($string);
    }

    public static function singularize(string $string): string
    {
        return self::inflection()->singularize($string);
    }

    public static function urlize(string $string): string
    {
        return self::inflection()->urlize($string);
    }

    public static function unaccent(string $string): string
    {
        return self::inflection()->unaccent($string);
    }
}