<?php

namespace App\Command;

use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:users:list',
    description: 'List all user emails in the system',
)]
class ListUsersCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $users = $this->userRepository->findAll();

        if (empty($users)) {
            $io->warning('No users found in the system.');
            return Command::SUCCESS;
        }

        $io->title('All Users in the System');

        $rows = [];
        foreach ($users as $user) {
            $rows[] = [
                'ID' => $user->getId(),
                'Email' => $user->getEmail(),
                'Roles' => implode(', ', $user->getRoles()),
                'Verified' => $user->isVerified() ? 'Yes' : 'No',
            ];
        }

        $io->table(
            ['ID', 'Email', 'Roles', 'Verified'],
            $rows
        );

        $io->success(sprintf('Total users: %d', count($users)));

        return Command::SUCCESS;
    }
}
