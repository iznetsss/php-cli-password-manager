<?php

declare(strict_types=1);

namespace App\Command;

use App\Support\Audit;
use App\Support\ConsoleSanitizer;
use App\Vault\VaultAccess;
use App\Vault\VaultRepository;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\Question;

final class DeleteCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('delete')
            ->setDescription('Delete credential by id')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Entry id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = new QuestionHelper();

        $id = (string)$input->getOption('id');
        if ($id === '') {
            $q = new Question('Entry ID: ');
            $id = (string)$helper->ask($input, $output, $q);
        }
        if (!Uuid::isValid($id) || Uuid::fromString($id)->getVersion() !== 4) {
            $output->writeln('<error>Invalid id</error>');
            return self::FAILURE;
        }

        $ok = VaultAccess::withUnlocked($input, $output, function (array $data) use ($output, $id): ?array {
            $state = VaultRepository::state($data);
            try {
                $res = VaultRepository::delete($state, $id);
            } catch (RuntimeException $e) {
                Audit::log('entry.delete.fail', 'fail', 404, ['reason' => 'not_found']);
                $output->writeln('<error>Not found</error>');
                return null;
            }

            $output->writeln('<info>Deleted</info>');
            $output->writeln('ID: ' . ConsoleSanitizer::safe($id));
            Audit::log('entry.delete', 'success', 0, ['id' => $id]);

            return $res['state'];
        });

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
