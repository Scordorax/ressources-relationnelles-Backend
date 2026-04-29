<?php

namespace App\Service\Category;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;

class CategoryService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CategoryRepository $repository
    ) {}

    public function getAll(): array
    {
        return $this->repository->findAll();
    }

    public function getOne(Category $category): Category
    {
        return $category;
    }

    public function create(array $data): Category
    {
        if (empty($data['name'])) {
            throw new \InvalidArgumentException("Le nom est requis");
        }

        $category = new Category();
        $category->setName($data['name']);
        $category->setDescription($data['description'] ?? null);

        $this->em->persist($category);
        $this->em->flush();

        return $category;
    }

    public function update(Category $category, array $data): Category
    {
        if (!empty($data['name'])) {
            $category->setName($data['name']);
        }

        if (array_key_exists('description', $data)) {
            $category->setDescription($data['description']);
        }

        $this->em->flush();

        return $category;
    }

    public function delete(Category $category): void
    {
        $this->em->remove($category);
        $this->em->flush();
    }
}
