<?php

namespace App\Controller;

use App\Form\VideojocType;
use App\Repository\VideojocRepository;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Videojoc;
use App\Form\VotacioType;
use App\Repository\VotacioRepository;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Faker\Factory;
use App\Entity\Usuari;
use App\Entity\Votacio;
use App\Form\FiltrarPerPreuType;
use Doctrine\ORM\EntityManager;

/**
 * @Route("/api/v1")
 */

class VideojocController extends AbstractFOSRestController
{
    function Search($search_value, $array_name)
    {
        return (array_search($search_value, $array_name));
    }
    /**
     * @Rest\Get(path="/videojocs", name="api_llistar_jocs")
     * @Rest\View(serializerGroups={"videojoc","genere","plataforma"}, serializerEnableMaxDepthChecks=true)
     */
    public function llistar(VideojocRepository $vr, Request $request)
    {
        //Array de filtratge de claus
        $arrayKeys = ["id", "preu", "fechaEstreno"];
        $sort = $request->query->get("sort") ?? "ASC";
        //Obtinc parametro
        $parametro = $request->query->get("parametro");
        $results = $request->query->get("results") ?? 10;

        //Si no existeix sempre filtrar per id
        $resultat = in_array($parametro, $arrayKeys) === true ? $parametro : "id";
        $page = $request->query->get('page', 1);

        $pagesCount = ceil($vr->count([]) / $results);
        $pages = range(1, $pagesCount);

        $videojocs = $vr->findBy([], [$resultat => $sort], $results, ($results * ($page - 1)));
        foreach ($videojocs as $videojoc) {
            if (!str_contains($videojoc->getPortada(), "http")) {
                $videojoc->setPortada("http://vos.es/uploads/portades_directory/" . $videojoc->getPortada());
            }
        }
        return $this->view(["Titul" => "Pàgina actual $page", "Tamany" => $pagesCount, "Resultat" => $videojocs]);
    }

    /**
     * @Rest\Get(path="/admin/videojocs/", name="api_llistar_tots_jocs")
     * @Rest\View(serializerGroups={"videojoc","genere","plataforma"}, serializerEnableMaxDepthChecks=true)
     */
    public function methodName(EntityManagerInterface $emi)
    {
        $videojocs = $emi->getRepository(Videojoc::class)->findAll();
        return $this->view(["Title"=>"Resultat Jocs","Jocs"=>$videojocs],Response::HTTP_OK);
    }

    /**
     * @Rest\Get(path="/videojoc/{id}", name="api_conseguir_videojoc")
     * @Rest\View(serializerGroups={"videojoc","genere","plataforma"}, serializerEnableMaxDepthChecks=true)
     */
    public function conseguirVideojoc(int $id, VideojocRepository $vr)
    {
        $videojoc = $vr->find($id);
        $nVotacions = count($videojoc->getVotacionsJoc());
        if (!str_contains($videojoc->getPortada(), "http")) {
            $videojoc->setPortada("http://vos.es/uploads/" . $videojoc->getPortada());
        }
        return $this->view(["Title" => "Videojoc", "NumeroVotacions" => $nVotacions, "Videojoc" => $videojoc]);
    }

    /**
     * @Rest\Get(path="/videojoc/titol/{titol}", name="api_conseguir_videojoc_titol")
     * @Rest\View(serializerGroups={"videojoc","genere","plataforma"}, serializerEnableMaxDepthChecks=true)
     */
    public function conseguirVideojocPerTitol(string $titol, VideojocRepository $vr)
    {
        $videojoc = $vr->findOneBy(["titul" => $titol], []);
        // $videojoc->setTitul("hola");
        $nVotacions = count($videojoc->getVotacionsJoc());
        if (!str_contains($videojoc->getPortada(), "http")) {
            $videojoc->setPortada("http://vos.es/uploads/" . $videojoc->getPortada());
        }
        return $this->view(["Title" => "Videojoc", "NumeroVotacions" => $nVotacions, "Videojoc" => $videojoc]);
    }

    /**
     * @Rest\Get(path="/videojoc/filtrar/preu/{min}/{max}", name="api_conseguir_filtrar_preu")
     * @Rest\View(serializerGroups={"videojoc","genere","plataforma"}, serializerEnableMaxDepthChecks=true)
     */
    public function filtrarVideojocPreu(int $min, int $max, Request $request, VideojocRepository $vr)
    {
        $form = $this->createForm(FiltrarPerPreuType::class, null);
        $form->handleRequest($request);

        $videojocs = $vr->filterByPrice($min, $max);
        return $this->view(["Title" => "Videojoc per preu $min i $max", "cantitat" => count($videojocs), "Videojoc" => $videojocs]);
        // return $this->view(["Title" => "No hi han resultats", "Videojoc" => "Valor buit"]);
    }

    /**
     * @Rest\Post(path="/videojoc/nou", name="api_insertar_joc")
     * @Rest\View(serializerGroups={"videojoc","genere","plataforma"}, serializerEnableMaxDepthChecks=true)
     */
    public function insertarVideojoc(EntityManagerInterface $emi, Request $request, SluggerInterface $slugger)
    {
        $videojoc = new Videojoc();
        $form = $this->createForm(VideojocType::class, $videojoc);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $brochureFile = $form->get('portada')->getData();
            if ($brochureFile) {
                $originalFilename = pathinfo($brochureFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $brochureFile->guessExtension();
                try {
                    $brochureFile->move(
                        $this->getParameter('portades_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                }


                $videojoc->setPortada($newFilename);
            } else {
                $faker = Factory::create();
                $videojoc->setPortada($faker->imageUrl(640, 480, 'animals', true));
            }
            $emi->persist($videojoc);
            $emi->flush();
            return ($this->view(["Title"=>"Videojoc pujat de manera satisfactoria","Videjoc"=>$videojoc], Response::HTTP_CREATED));
        }
        return $this->view($form, Response::HTTP_BAD_REQUEST);
    }

    /**
     * @Rest\Put(path="/videojoc/{id}/editar", name="api_editar_joc")
     * @Rest\View(serializerGroups={"videojoc"}, serializerEnableMaxDepthChecks=true)
     */
    public function editarVideojoc(int $id, EntityManagerInterface $emi, Request $request, SluggerInterface $slugger)
    {
        $videojoc = $emi->find(Videojoc::class, $id);
        if (!$videojoc) {
            $this->createNotFoundException();
        }
        $form = $this->createForm(VideojocType::class, $videojoc);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $brochureFile = $form->get('portada')->getData();
            if ($brochureFile) {
                $originalFilename = pathinfo($brochureFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $brochureFile->guessExtension();
                try {
                    $brochureFile->move(
                        $this->getParameter('portades_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                }


                $videojoc->setPortada($newFilename);
            }
            $emi->flush();
            return ($this->view($videojoc, Response::HTTP_CREATED));
        }
        return $this->view($form, Response::HTTP_BAD_REQUEST);
    }

    /**
     * @Rest\Delete(path="/videojoc/{id}/borrar", name="api_borrar_joc")
     * @Rest\View(serializerGroups={"videojoc"}, serializerEnableMaxDepthChecks=true)
     */
    public function borrarVideojoc(int $id, EntityManagerInterface $emi)
    {
        $videojoc = $emi->find(Videojoc::class, $id);
        if (!$videojoc) {
            $this->createNotFoundException();
        }
        $jocAuxiliar = $videojoc;
        $emi->remove($videojoc);
        $emi->flush();
        return $this->view(["Titol" => "Borrat joc de manera satisfactoria", "Resultat" => $jocAuxiliar], 200);
    }


    /**
     * @Rest\Get(path="/videojoc/buscar/{slug}", name="api_buscar_joc")
     * @Rest\View(serializerGroups={"videojoc"}, serializerEnableMaxDepthChecks=true)
     */
    public function buscarVideojoc(String $slug, VideojocRepository $vr)
    {
        $jocAuxiliar = $vr->obtindreJocBuscanElTitol($slug);
        return $this->view(["Titol" => "Buscar joc amb titol $slug", "Resultat" => $jocAuxiliar], 200);
    }

    /**
     * @Rest\Get(path="/videojoc/{id}/comentaris", name="api_llistar_comentaris")
     * @Rest\View(serializerGroups={"videojoc","votacio","usuari"}, serializerEnableMaxDepthChecks=true)
     */
    public function obtindreComentarisVideojocs(int $id, VotacioRepository $emi)
    {

        $votacions = $emi->findBy(["videojoc" => $id], []);
        if (!$votacions) {
            return $this->createNotFoundException();
        }
        return $votacions;
    }

    /**
     * @Rest\Post(path="/videojoc/{id}/usuari/{ide}/comentari/nou", name="api_insertar_comentaris")
     * @Rest\View(serializerGroups={"videojoc","votacio","usuari"}, serializerEnableMaxDepthChecks=true)
     */
    public function insertarComentariVideojocs(int $id, int $ide, EntityManagerInterface $mr, Request $request, VotacioRepository $emi)
    {


        $usuari = $mr->getRepository(Usuari::class)->find($ide);
        if (!$usuari) {
            $this->createNotFoundException("Usuari no trobat");
        }
        $videojoc = $mr->getRepository(Videojoc::class)->find($id);
        $votacions = $emi->findBy(["usuari_votacio" => $usuari, "videojoc" => $id], []);
        if (!$votacions) {
            $this->createNotFoundException();
        }
        if (count($votacions) === 0) {
            $this->createNotFoundException();
        }
        if ($votacions) {
            return $this->view(["title" => "No pots tornar a votar " . $usuari->getEmail(), "votacio" => $votacions], Response::HTTP_BAD_REQUEST);
        }
        $votacio = new Votacio();
        $form = $this->createForm(VotacioType::class, $votacio);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $votacio->setUsuariVotacio($usuari)
                ->setVideojoc($videojoc);
            $mr->persist($votacio);
            $mr->flush();
            return $this->view(["Title" => "Votacio insertada", "result" => $votacio]);
        }
        return $this->view([$form]);
    }
}
