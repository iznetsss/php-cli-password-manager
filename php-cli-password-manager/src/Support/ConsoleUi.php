<?php
declare(strict_types=1);

namespace App\Support;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\Question;

final class ConsoleUi
{
    /** Clear screen + scrollback where supported */
    public static function clear(OutputInterface $output): void
    {
        $output->write("\033[2J\033[3J\033[H");
    }

    /** Wait for any key (raw mode) with Enter fallback */
    public static function waitAnyKey(InputInterface $input, OutputInterface $output, string $prompt = '[press any key]'): void
    {
        $output->writeln('');
        // Try raw TTY mode on *nix
        if (\DIRECTORY_SEPARATOR === '/' && function_exists('shell_exec')) {
            $st = @shell_exec('stty -g');
            if (is_string($st)) {
                $output->write($prompt);
                @shell_exec('stty -icanon -echo min 1 time 0');
                $fp = @fopen('php://stdin', 'rb');
                if (is_resource($fp)) {
                    @fread($fp, 1);
                    @fclose($fp);
                }
                @shell_exec('stty ' . $st);
                $output->writeln('');
                return;
            }
        }
        new QuestionHelper()->ask($input, $output, new Question($prompt . ' (Enter) '));
    }
}
