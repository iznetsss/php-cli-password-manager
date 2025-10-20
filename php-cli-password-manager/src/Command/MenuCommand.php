<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

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
            $list = [];
            foreach ($app->all() as $name => $cmd) {
                if (
                    in_array($name, ['menu', 'list', 'help', '_complete', 'completion'], true) ||
                    str_starts_with($name, '_')
                ) {
                    continue;
                }
                $list[] = [
                    'name' => $name,
                    'desc' => (string) $cmd->getDescription(),
                ];
            }

            $output->writeln('');
            $output->writeln('<info>Available commands:</info>');
            foreach ($list as $i => $c) {
                $output->writeln(sprintf('%d) pm %s — %s', $i + 1, $c['name'], $c['desc']));
            }
            $output->writeln('0) Exit');

            $helper = new QuestionHelper();
            $q = new Question('Select number: ');
            $answer = trim((string) $helper->ask($input, $output, $q));

            if ($answer === '0' || $answer === '') {
                $output->writeln('<comment>Bye</comment>');
                return self::SUCCESS;
            }

            if (!ctype_digit($answer)) {
                $output->writeln('<error>Invalid choice</error>');
                continue;
            }

            $idx = (int) $answer - 1;
            if (!isset($list[$idx])) {
                $output->writeln('<error>Invalid choice</error>');
                continue;
            }

            $chosen = $list[$idx]['name'];

            $exit = $app->run(new StringInput($chosen), $output);


            if ($exit !== 0) {
                $output->writeln('<comment>Command finished with errors</comment>');
            }
        }
    }
}
