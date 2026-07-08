<?php

namespace App\Command;

use App\Service\TaskService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:task:create',
    description: 'Создает новую задачу в интерактивном режиме',
)]
class CreateTaskCommand extends Command
{
    public function __construct(private TaskService $taskService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Создание новой задачи');

        // Интерактивный режим
        $title = $io->ask('Введите название задачи', null, function ($value) {
            if (empty($value)) {
                throw new \RuntimeException('Название не может быть пустым!');
            }
            return $value;
        });

        // опциональное описание
        $description = $io->ask('Введите описание задачи (опционально)');

        // Вызываем сервис
        $task = $this->taskService->createTask($title, $description);

        //в ответ выводится JSON, содержащий все данные о задаче
        $jsonResult = json_encode([
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'status' => $task->getStatus(),
            'created_at' => $task->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $task->getUpdateAt()->format(\DateTimeInterface::ATOM),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $output->writeln($jsonResult);

        return Command::SUCCESS;
    }
}