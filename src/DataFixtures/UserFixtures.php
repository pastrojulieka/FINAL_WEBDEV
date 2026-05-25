<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{   
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }
    
    public function load(ObjectManager $manager): void
    {
        $existingAdmin = $manager->getRepository(User::class)->findOneBy(['email' => 'admin@gmail.com']);
        if (!$existingAdmin) {
            $admin = new User();
            $admin->setEmail('admin@gmail.com');
            $admin->setRoles(['ROLE_ADMIN']);
            $hashedPassword = $this->passwordHasher->hashPassword($admin, 'admin123');
            $admin->setPassword($hashedPassword);
            $admin->setIsVerified(true);
            $admin->setVerificationToken(null);
            $manager->persist($admin);
        }

        $existingUser = $manager->getRepository(User::class)->findOneBy(['email' => 'pastro@gmail.com']);
        if (!$existingUser) {
            $user = new User();
            $user->setEmail('pastro@gmail.com');
            $user->setRoles(['ROLE_USER']);
            $hashedPassword = $this->passwordHasher->hashPassword($user, 'user123');
            $user->setPassword($hashedPassword);
            $user->setIsVerified(true);
            $user->setVerificationToken(null);
            $manager->persist($user);
        }

        $existingStaff = $manager->getRepository(User::class)->findOneBy(['email' => 'staff@gmail.com']);
        if (!$existingStaff) {
            $staff = new User();
            $staff->setEmail('staff@gmail.com');
            $staff->setRoles(['ROLE_STAFF']);
            $hashedPassword = $this->passwordHasher->hashPassword($staff, 'staff123');
            $staff->setPassword($hashedPassword);
            $staff->setIsVerified(true);
            $staff->setVerificationToken(null);
            $manager->persist($staff);
        }

        $manager->flush();
    }
}