<?php

namespace App\Command;

use App\Repository\TaskRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:task:list',
    description: 'Выводит список задач в виде таблицы',
)]
class ListTasksCommand extends Command
{
    public function __construct(private TaskRepository $taskRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        // Добавляем опциональный аргумент для фильтрации по статусу
        $this->addArgument('status', InputArgument::OPTIONAL, 'Фильтр по статусу задач');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $statusFilter = $input->getArgument('status');

        // Ищем задачи с фильтром или без
        if ($statusFilter) {
            $tasks = $this->taskRepository->findBy(['status' => $statusFilter]);
        } else {
            $tasks = $this->taskRepository->findAll();
        }

        if (empty($tasks)) {
            $io->warning('Задачи не найдены.');
            return Command::SUCCESS;
        }

        // Формируем строки для таблицы
        $rows = [];
        foreach ($tasks as $task) {
            $rows[] = [
                $task->getId(),
                $task->getTitle(),
                $task->getDescription() ?? '-',
                $task->getStatus(),
                $task->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        // Рисуем таблицу в консоли
        $io->table(
            ['ID', 'Название', 'Описание', 'Статус', 'Создана'],
            $rows
        );

        return Command::SUCCESS;
    }
}