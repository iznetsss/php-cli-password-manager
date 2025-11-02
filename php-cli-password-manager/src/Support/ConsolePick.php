<?php
declare(strict_types=1);

namespace App\Support;

use App\Validation\InputValidator;
use App\Validation\ValidationException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

final class ConsolePick
{
    /** Build unique, sorted services */
    public static function services(array $entries): array
    {
        $map = [];
        foreach ($entries as $e) $map[(string)($e['service'] ?? '')] = true;
        $services = array_keys($map);
        sort($services, SORT_NATURAL | SORT_FLAG_CASE);
        return $services;
    }

    /** Print numbered services */
    public static function printServices(OutputInterface $output, array $services): void
    {
        $output->writeln('Services:');
        foreach ($services as $i => $name) {
            $output->writeln(sprintf('%d. %s', $i + 1, ConsoleSanitizer::safe($name)));
        }
    }

    /** Ask service (number or name) and validate */
    public static function askService(
        InputInterface  $input,
        OutputInterface $output,
        QuestionHelper  $helper,
        array           $services
    ): ?string
    {
        $ans = trim((string)$helper->ask($input, $output, new Question('Please, choose service: ')));
        $sel = $ans;
        if (ctype_digit($ans) && $ans !== '0') {
            $idx = (int)$ans - 1;
            if (isset($services[$idx])) $sel = $services[$idx];
        }
        try {
            return InputValidator::service($sel);
        } catch (ValidationException) {
            $output->writeln('<error>Invalid service</error>');
            return null;
        }
    }

    /** Print candidate entries for a service */
    public static function printEntries(OutputInterface $output, array $candidates): void
    {
        $output->writeln('Entries:');
        foreach ($candidates as $i => $e) {
            $output->writeln(sprintf('%d) %s [%s]', $i + 1, ConsoleSanitizer::safe($e['username'] ?? ''), substr((string)($e['id'] ?? ''), 0, 8)));
        }
    }

    /** Pick one entry by number, default = first */
    public static function pickEntry(
        InputInterface  $input,
        OutputInterface $output,
        QuestionHelper  $helper,
        array           $candidates
    ): array
    {
        if (count($candidates) === 1) return $candidates[0];
        self::printEntries($output, $candidates);
        $pick = trim((string)$helper->ask($input, $output, new Question('Choose entry number: ')));
        if (ctype_digit($pick) && $pick !== '0' && isset($candidates[(int)$pick - 1])) {
            return $candidates[(int)$pick - 1];
        }
        return $candidates[0];
    }
}
