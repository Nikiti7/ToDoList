<?php

namespace App\Controller\Api;

use App\Entity\Task;
use App\Enum\TaskStatus;
use App\Repository\TaskRepository;
use App\Service\TaskService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/tasks')]
class TaskController extends AbstractController
{
    public function __construct(
        private TaskService $taskService,
        private TaskRepository $taskRepository
    ) {}

    // 1. Получение списка задач (GET) с фильтрацией
    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $status = $request->query->get('status');
        $from = $request->query->get('from');
        $to = $request->query->get('to');

        $qb = $this->taskRepository->createQueryBuilder('t');

        if ($status) {
            $qb->andWhere('t.status = :status')
               ->setParameter('status', $status);
        }

        if ($from) {
            $qb->andWhere('t.updateAt >= :from')
               ->setParameter('from', new \DateTimeImmutable($from));
        }

        if ($to) {
            $qb->andWhere('t.updateAt <= :to')
               ->setParameter('to', new \DateTimeImmutable($to));
        }

        $tasks = $qb->getQuery()->getResult();

        return $this->json($this->serializeTasks($tasks));
    }

    // 2. Получение задачи по ID (GET)

    #[Route('/{id}', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $task = $this->taskRepository->find($id);

        if (!$task) {
            return $this->json(['error' => 'Task not found'], 404);
        }

        return $this->json($this->serializeTask($task));
    }

    // 3. Создание задачи (POST)
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['title'])) {
            return $this->json(['error' => 'Title is required'], 400);
        }

        $task = $this->taskService->createTask(
            $data['title'], 
            $data['description'] ?? null
        );

        return $this->json($this->serializeTask($task), 201);
    }

    // 4. Изменение статуса задачи (PUT)
    #[Route('/{id}/status', methods: ['PUT'])]
    public function updateStatus(int $id, Request $request): JsonResponse
    {
        $task = $this->taskRepository->find($id);

        if (!$task) {
            return $this->json(['error' => 'Task not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $newStatusStr = $data['status'] ?? null;

        $newStatus = TaskStatus::tryFrom($newStatusStr);
        if (!$newStatus) {
            return $this->json(['error' => 'Invalid status'], 400);
        }

        $this->taskService->changeStatus($task, $newStatus);

        return $this->json($this->serializeTask($task));
    }

    // Вспомогательные методы для быстрой сериализации объектов в массив.
    private function serializeTask(Task $task): array
    {
        return [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'status' => $task->getStatus(),
            'created_at' => $task->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $task->getUpdateAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function serializeTasks(array $tasks): array
    {
        return array_map([$this, 'serializeTask'], $tasks);
    }
}