<?php

namespace App\Service;

use App\Entity\Task;
use App\Enum\TaskStatus;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class TaskService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TaskRepository $taskRepository,
        private LoggerInterface $logger
    ) {}

    // Создание новой задачи с проверкой лимита очереди.

    public function createTask(string $title, ?string $description): Task
    {
        $task = new Task();
        $task->setTitle($title);
        $task->setDescription($description);
        $task->setCreatedAt(new \DateTimeImmutable());
        $task->setUpdateAt(new \DateTimeImmutable());

        $newTasksCount = $this->taskRepository->count(['status' => TaskStatus::NEW->value]);

        if ($newTasksCount >= 5) {
            $task->setStatus(TaskStatus::PENDING->value);
        } else {
            $task->setStatus(TaskStatus::NEW->value);
        }

        $this->em->persist($task);
        $this->em->flush();

        $this->logger->info('Задача создана', [
            'id' => $task->getId(), 
            'status' => $task->getStatus()
        ]);

        return $task;
    }

    // Смена статуса конкретной задачи + продвижение задачи из очереди pending.

    public function changeStatus(Task $task, TaskStatus $newStatus): Task
    {
        $oldStatus = $task->getStatus();
        
        if ($oldStatus === $newStatus->value) {
            return $task;
        }

        $task->setStatus($newStatus->value);
        $task->setUpdateAt(new \DateTimeImmutable());

        if ($oldStatus === TaskStatus::NEW->value) {
            $this->promoteNextPendingTask();
        }

        $this->em->flush();

        $this->logger->info('Статус задачи изменен', [
            'id' => $task->getId(), 
            'old_status' => $oldStatus, 
            'new_status' => $newStatus->value
        ]);

        return $task;
    }

    // Массовый перевод всех 'new' задач в 'in_progress'
    public function promoteAllNewToInProgress(): int
    {
        $newTasks = $this->taskRepository->findBy(['status' => TaskStatus::NEW->value]);
        $count = 0;

        foreach ($newTasks as $task) {
            $task->setStatus(TaskStatus::IN_PROGRESS->value);
            $task->setUpdateAt(new \DateTimeImmutable());
            $count++;
            
            $this->promoteNextPendingTask();
        }

        $this->em->flush();
        
        $this->logger->info("Массовое обновление статусов: $count задач переведено в in_progress");

        return $count;
    }

    // Вспомогательный метод: вытаскивает самую старую задачу из pending и делает её new
    private function promoteNextPendingTask(): void
    {
        $nextPending = $this->taskRepository->findOneBy(
            ['status' => TaskStatus::PENDING->value],
            ['createdAt' => 'ASC']
        );

        if ($nextPending) {
            $nextPending->setStatus(TaskStatus::NEW->value);
            $nextPending->setUpdateAt(new \DateTimeImmutable());
            
            $this->logger->info('Задача продвинута из очереди pending в new', [
                'id' => $nextPending->getId()
            ]);
        }
    }
}