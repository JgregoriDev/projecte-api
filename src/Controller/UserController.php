<?php

namespace App\Controller;

use App\Entity\Genere;
use App\Entity\Usuari;
use App\Form\VideojocType;
use App\Repository\VideojocRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Form\UsuariType;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Videojoc;
use App\Form\BanejarType;
use App\Form\GeneresType;
use App\Form\UsuariLoginType;
use App\Repository\GenereRepository;
use App\Repository\UsuariRepository;
use Doctrine\ORM\Mapping\Entity;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @Route("/api")
 */

class UserController extends AbstractFOSRestController
{
    /**
     * @Rest\Get(path="/v1/usuaris", name="api_llistar_usuari")
     * @Rest\View(serializerGroups={"usuari"}, serializerEnableMaxDepthChecks=true)
     */
    public function llistar(UsuariRepository $ur)
    {
        return $ur->findAll();
    }

    /**
     * @Rest\Post(path="/login", name="api_login_usuari")
     * @Rest\View(serializerGroups={"usuari"}, serializerEnableMaxDepthChecks=true)
     */
    public function login(
        UsuariRepository $ur,
        Request $request,
        JWTTokenManagerInterface $JWTManager,
        UserPasswordHasherInterface $passwordHasher
    ) {
        $usuari = new Usuari();
        $form = $this->createForm(UsuariLoginType::class, $usuari);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $password = $usuari->getPassword();
            // $usuari->setPassword($hashedPassword);
            $user = $ur->findOneBy(["email" => $usuari->getEmail()]);
            if ($user->isBan()) {
                return $this->view(["Title" => "Usuari banejat", "usuari" => "Usuari banejat"], Response::HTTP_FORBIDDEN);
            }
            if ($passwordHasher->isPasswordValid($user, $password)) {
                return ($this->view(["Title" => "Login correcte", "id" => $user->getId(), 'email' => $user->getEmail(), 'token' => $JWTManager->create($user)], 200));
            }
            return ($this->view(["Title" => "Login incocorrecte", 'email' => $user->getEmail()], Response::HTTP_NOT_FOUND));
        }
        return $this->view($form, Response::HTTP_BAD_REQUEST);
    }

    /**
     * @Rest\Post(path="/v1/usuari/cambiar/contrasenya", name="api_insertar_genere")
     * @Rest\View(serializerGroups={"genere"}, serializerEnableMaxDepthChecks=true)
     */
    public function insertarGenere(EntityManagerInterface $emi, Request $request, UserPasswordHasherInterface $passwordHasher)
    {
        $genere = new Usuari();
        $form = $this->createForm(GeneresType::class, $genere);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $emi->persist($genere);
            $emi->flush();
            return ($this->view($genere, Response::HTTP_OK));
        }
        return $this->view($form, Response::HTTP_BAD_REQUEST);
    }

    /**
     * @Rest\Post(path="/v1/usuari/{id}/banejar", name="api_ban_usuari")
     * @Rest\View(serializerGroups={"usuari"}, serializerEnableMaxDepthChecks=true)
     */
    public function banejarUsuari(int $id, EntityManagerInterface $emi, Request $request, UserPasswordHasherInterface $passwordHasher)
    {
        $usuari = $emi->getRepository(Usuari::class)->find($id);
        if (!$usuari) {
            $this->createNotFoundException("Usuari no trobat");
        }
        // $form = $this->createForm(BanejarType::class, $usuari);
        // $form->handleRequest($request);


        $usuari->setBan(!$usuari->isBan());
        $emi->flush();
        if ($usuari->isBan()) {
            return ($this->view(["Title" => "Usuari banejat", "Resultat" => $usuari], Response::HTTP_OK));
        } else {
            return ($this->view(["Title" => "Usuari desbanejat", "Resultat" => $usuari], Response::HTTP_OK));
        }
    }

    /**
     * @Rest\Post(path="/v1/registrar", name="api_usuari_nou")
     * @Rest\View(serializerGroups={"usuari"}, serializerEnableMaxDepthChecks=true)
     */
    public function newUser(EntityManagerInterface $emi, Request $request, UserPasswordHasherInterface $passwordHasher)
    {   
        $usuari=new Usuari();
    
        $form = $this->createForm(UsuariType::class, $usuari);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $pass=$passwordHasher->hashPassword($usuari,$usuari->getPassword());
            $usuari
            ->setBan(false)
            ->setRoles(["ROLE_USER"])
            ->setPassword($pass);
            $emi->persist($usuari);
            $emi->flush();
            return ($this->view(["Title"=>"usuari registrat","Usuari"=>$usuari], Response::HTTP_OK));
        }
        return $this->view($form, Response::HTTP_BAD_REQUEST);

    }
        /**
     * @Rest\Get(path="/v1/pago", name="api_token_pay")
     * @Rest\View(serializerGroups={"usuari"}, serializerEnableMaxDepthChecks=true)
     */
    public function payment()
    {
        $parameter=$this->getParameter("token_pay");
        return ($this->view(["Title"=>"usuari registrat","Usuari"=>$parameter], Response::HTTP_OK));
    }
}
