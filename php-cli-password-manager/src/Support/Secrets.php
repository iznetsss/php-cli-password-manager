<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

final class Secrets
{
    // Hidden prompt for secrets
    public static function askHidden(InputInterface $input, OutputInterface $output, string $prompt): string
    {
        $helper = new QuestionHelper();
        $q = new Question($prompt);
        $q->setHidden(true)->setHiddenFallback(false);
        return (string)$helper->ask($input, $output, $q);
    }

    // Standard placeholder when not showing secrets
    public static function hiddenPlaceholder(): string
    {
        return '[hidden]';
    }
}
