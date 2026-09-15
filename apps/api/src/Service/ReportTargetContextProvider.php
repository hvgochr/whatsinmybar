<?php

namespace App\Service;

use App\Entity\Comment;
use App\Entity\Recipe;
use App\Entity\Report;
use App\Entity\User;
use App\Enum\ReportTargetType;
use App\Repository\CommentRepository;
use App\Repository\RecipeRepository;
use App\Repository\UserRepository;

final readonly class ReportTargetContextProvider
{
    public function __construct(
        private RecipeRepository $recipes,
        private CommentRepository $comments,
        private UserRepository $users,
    ) {
    }

    /**
     * @param list<Report> $reports
     *
     * @return array<int, array<string, mixed>|null>
     */
    public function forReports(array $reports): array
    {
        $idsByType = [
            ReportTargetType::Recipe->value => [],
            ReportTargetType::Comment->value => [],
            ReportTargetType::User->value => [],
        ];
        foreach ($reports as $report) {
            $idsByType[$report->getTargetType()->value][] = $report->getTargetId();
        }

        $targets = [
            ReportTargetType::Recipe->value => $this->byId($this->recipes->findForReportContextByIds($idsByType[ReportTargetType::Recipe->value])),
            ReportTargetType::Comment->value => $this->byId($this->comments->findForReportContextByIds($idsByType[ReportTargetType::Comment->value])),
            ReportTargetType::User->value => $this->byId($idsByType[ReportTargetType::User->value] ? $this->users->findBy(['id' => $idsByType[ReportTargetType::User->value]]) : []),
        ];

        $contexts = [];
        foreach ($reports as $report) {
            $target = $targets[$report->getTargetType()->value][$report->getTargetId()] ?? null;
            $contexts[(int) $report->getId()] = $this->context($target);
        }

        return $contexts;
    }

    /**
     * @param list<Recipe|Comment|User> $entities
     *
     * @return array<int, Recipe|Comment|User>
     */
    private function byId(array $entities): array
    {
        $indexed = [];
        foreach ($entities as $entity) {
            if (null !== $entity->getId()) {
                $indexed[$entity->getId()] = $entity;
            }
        }

        return $indexed;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function context(Recipe|Comment|User|null $target): ?array
    {
        return match (true) {
            $target instanceof Recipe => [
                'type' => ReportTargetType::Recipe->value,
                'title' => $target->getTitle(),
                'slug' => $target->getSlug(),
                'authorUsername' => $target->getAuthorUsername(),
                'description' => $target->getDescription(),
                'status' => $target->getStatus()->value,
                'moderationStatus' => $target->getModerationStatus()->value,
                'deleted' => null !== $target->getDeletedAt(),
            ],
            $target instanceof Comment => [
                'type' => ReportTargetType::Comment->value,
                'recipeSlug' => $target->getRecipe()->getSlug(),
                'authorUsername' => $target->getAuthor()->getUsername(),
                'message' => $target->getMessage(),
                'moderationStatus' => $target->getModerationStatus()->value,
                'deleted' => null !== $target->getDeletedAt(),
            ],
            $target instanceof User => [
                'type' => ReportTargetType::User->value,
                'username' => $target->getUsername(),
                'email' => $target->getEmail(),
                'bio' => $target->getBio(),
                'roles' => $target->getRoles(),
                'deleted' => null !== $target->getDeletedAt(),
            ],
            default => null,
        };
    }
}
