<?php

namespace App\Command;

use App\Enum\TaskStatus;
use App\Repository\TaskRepository;
use App\Service\TaskService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:task:change-status',
    description: 'Меняет статус задачи',
)]
class ChangeStatusCommand extends Command
{
    public function __construct(
        private TaskService $taskService,
        private TaskRepository $taskRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::OPTIONAL, 'ID задачи')
            ->addArgument('status', InputArgument::OPTIONAL, 'Новый статус задачи');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = $input->getArgument('id');
        $statusStr = $input->getArgument('status');

        // Если аргументы не указаны, все задачи в статусе new переводятся в статус in_progress
        if (!$id && !$statusStr) {
            $count = $this->taskService->promoteAllNewToInProgress();
            $io->success("Успешно! $count задач из статуса 'new' переведено в 'in_progress'.");
            return Command::SUCCESS;
        }

        // Если указан только один из аргументов — это ошибка ввода
        if (!$id || !$statusStr) {
            $io->error('Необходимо указать ЛИБО оба аргумента (id и статус), ЛИБО не указывать их вовсе.');
            return Command::FAILURE;
        }

        // Ищем конкретную задачу
        $task = $this->taskRepository->find((int)$id);
        if (!$task) {
            $io->error("Задача с ID $id не найдена.");
            return Command::FAILURE;
        }

        // Валидируем статус через Enum
        $newStatus = TaskStatus::tryFrom($statusStr);
        if (!$newStatus) {
            $io->error("Статус '$statusStr' не существует. Допустимые: new, in_progress, done, pending, cancelled");
            return Command::FAILURE;
        }

        // Меняем статус через сервис
        $this->taskService->changeStatus($task, $newStatus);
        $io->success("Статус задачи №{$id} успешно изменен на '{$statusStr}'.");

        return Command::SUCCESS;
    }
}