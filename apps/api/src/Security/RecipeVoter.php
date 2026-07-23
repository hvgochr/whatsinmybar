<?php

namespace App\Security;

use App\Entity\Recipe;
use App\Entity\User;
use App\Enum\RecipeModerationStatus;
use App\Enum\RecipeStatus;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Recipe>
 */
final class RecipeVoter extends Voter
{
    public function __construct(private readonly AlcoholAccessPolicy $alcoholAccessPolicy)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Recipe && in_array($attribute, [RecipeAccess::View, RecipeAccess::Manage], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return match ($attribute) {
            RecipeAccess::View => $this->canView($subject, $token),
            RecipeAccess::Manage => $this->canManage($subject, $token),
            default => false,
        };
    }

    private function canView(Recipe $recipe, TokenInterface $token): bool
    {
        if (null !== $recipe->getDeletedAt()) {
            return false;
        }

        $user = $token->getUser();
        $isAdmin = $user instanceof User && in_array('ROLE_ADMIN', $user->getRoles(), true);

        if (RecipeModerationStatus::Visible !== $recipe->getModerationStatus() && !$isAdmin) {
            return false;
        }

        if ($recipe->containsAlcohol() && !$this->alcoholAccessPolicy->canAccessAlcohol($token->getUser())) {
            return false;
        }

        if (RecipeStatus::Published === $recipe->getStatus()) {
            return true;
        }

        return $this->canManage($recipe, $token);
    }

    private function canManage(Recipe $recipe, TokenInterface $token): bool
    {
        if (null !== $recipe->getDeletedAt()) {
            return false;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        if (RecipeModerationStatus::Visible !== $recipe->getModerationStatus()) {
            return false;
        }

        if ($recipe->containsAlcohol() && !$this->alcoholAccessPolicy->canAccessAlcohol($user)) {
            return false;
        }

        $author = $recipe->getAuthor();
        if (null === $author) {
            return false;
        }

        if (null === $author->getId() || null === $user->getId()) {
            return $author === $user;
        }

        return $author->getId() === $user->getId();
    }
}
