<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Post;
use App\Entity\User;
use App\Form\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class UserController extends AbstractController
{
    
    private $em;

    public function __construct(EntityManagerInterface $em){
        $this->em = $em;
    }
    
    #[Route('/user', name: 'app_user')]
    public function index(): Response
    {
        $users = $this->em->getRepository(User::class)->findAll();
        return $this->render('user/index.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/user/insert', name: 'insert_user')]
    public function insertar(request $request, UserPasswordHasherInterface $passwordHasher, SluggerInterface $slugger): Response
    {
        $user = new User($request);
        $form = $this->createForm(userType::class, $user);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()){
            
            $file = $form->get('photo')->getData();

            if($file){
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();
                try {
                    $file->move(
                        $this->getParameter('files_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    throw new \Exception('problema al subir el archivo');
                }
                $user->setPhoto($newFilename);
            }
            
            $pass = $form->get('password')->getData();

            $hashedPassword = $passwordHasher->hashPassword(
                $user,
                $pass
            );
            $user->setPassword($hashedPassword);
            $user->setRoles(['Usuario']);

            $this->em->persist($user);
            $this->em->flush();
            return $this->redirectToRoute('app_user');
        }

        return $this->render('user/insertar.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/user/remove/{id}', name: 'delete_user')]
    public function eliminar($id): Response
    {
        $user = $this->em->getRepository(User::class)->find($id);
        $this->em->remove($user);
        $this->em->flush();
        return JsonResponse(['success' => true]);
    }
}
