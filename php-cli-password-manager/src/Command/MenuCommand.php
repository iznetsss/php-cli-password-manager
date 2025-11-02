<?php

declare(strict_types=1);

namespace App\Command;

use App\Support\ConsoleUi;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Throwable;

final class MenuCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('menu')->setDescription('Interactive menu');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $app = $this->getApplication();
        if ($app === null) {
            $output->writeln('<error>Application unavailable</error>');
            return self::FAILURE;
        }

        while (true) {
            ConsoleUi::clear($output);

            $commands = [];
            foreach ($app->all() as $name => $cmd) {
                if (in_array($name, ['menu', 'list', 'help', '_complete', 'completion'], true) || str_starts_with($name, '_')) {
                    continue;
                }
                $commands[] = ['name' => $name, 'desc' => $cmd->getDescription()];
            }

            $output->writeln('<info>Available commands:</info>');
            foreach ($commands as $i => $c) {
                $output->writeln(sprintf('%d) pm %s — %s', $i + 1, $c['name'], $c['desc']));
            }
            $output->writeln('0) Exit');

            $helper = new QuestionHelper();
            $answer = trim((string)$helper->ask($input, $output, new Question('Select number: ')));

            if ($answer === '0' || $answer === '') {
                $output->writeln('<comment>Bye</comment>');
                return self::SUCCESS;
            }

            if (!ctype_digit($answer)) {
                $output->writeln('<error>Invalid choice</error>');
                ConsoleUi::waitAnyKey($input, $output);
                continue;
            }

            $idx = (int)$answer - 1;
            if (!isset($commands[$idx])) {
                $output->writeln('<error>Invalid choice</error>');
                ConsoleUi::waitAnyKey($input, $output);
                continue;
            }

            $chosen = $commands[$idx]['name'];

            try {
                $cmd = $app->find($chosen);
                $exit = $cmd->run(new ArrayInput([]), $output);
                if ($exit !== 0) {
                    $output->writeln('<comment>Command finished with errors</comment>');
                }
            } catch (Throwable) {
                $output->writeln('<error>Command failed</error>');
            }

            // Centralized pause -> clear -> next loop shows clean menu
            ConsoleUi::waitAnyKey($input, $output);
        }
    }
}
