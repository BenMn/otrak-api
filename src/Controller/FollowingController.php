<?php

namespace App\Controller;

use App\Entity\Show;
use App\Entity\Type;
use App\Entity\User;
use App\Entity\Genre;
use App\Entity\Season;
use App\Entity\Episode;
use App\Entity\Network;
use App\Entity\Following;
use App\Repository\ShowRepository;
use App\Repository\TypeRepository;
use App\Repository\UserRepository;
use App\Repository\GenreRepository;
use App\Repository\SeasonRepository;
use App\Repository\EpisodeRepository;
use App\Repository\NetworkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * @Route("/api")
 */
class FollowingController extends AbstractController
{
    /**
     * @Route("/following/new/{id}/{status}/{showId}/{seasonNumber}/{episodeNumber}", requirements={"id"="\d+", "status"="\d+", "showId"="\d+", "seasonNumber"="\d+", "episodeNumber"="\d+"}, methods={"POST"})
     */
    public function new($id, $status, $showId, $seasonNumber, $episodeNumber, Request $request, UserRepository $userRepository, ShowRepository $showRepository, SeasonRepository $seasonRepository, EpisodeRepository $episodeRepository, TypeRepository $typeRepository, GenreRepository $genreRepository, NetworkRepository $networkRepository, EntityManagerInterface $em)
    {
        $show = $showRepository->findOneBy(['id_tvmaze' => $showId]);

        if (is_null($show)) {
            $showApi = ApiController::retrieveData('get', 'showComplete', $showId);
        
            $show = new Show();
            
            $show->setName($showApi->name);
            $show->setSummary($showApi->summary);
            $show->setPoster($showApi->image->original);
            $show->setWebsite($showApi->officialSite);
            $show->setRating($showApi->rating->average);
            $show->setLanguage($showApi->language);
            $show->setRuntime($showApi->runtime);
            $show->setIdTvmaze($showId);
            $show->setIdTvdb($showApi->externals->thetvdb);
            $show->setIdImdb($showApi->externals->imdb);
            $show->setApiUpdate($showApi->updated);

            $show->setStatus(0);

            switch ($showApi->status) {
                case 'Running':
                    $show->setStatus(1);
                    break;

                case 'Ended':
                    $show->setStatus(0);
                    break;
            }

            $type = $typeRepository->findOneByName($showApi->type);
            if (is_null($type)) {
                $type = new Type();
                $type->setName($showApi->type);
                $em->persist($type);

                $show->setType($type);
            } else $show->setType($type);

            $network = $networkRepository->findOneByName($showApi->network->name);
            if (is_null($network)) {
                $network = new Network();
                $network->setName($showApi->network->name);
                $em->persist($network);

                $show->setNetwork($network);
            } else $show->setNetwork($network);

            foreach ($showApi->genres as $currentGenre) {
                $genre = $genreRepository->findOneByName($currentGenre);

                if (is_null($genre)) {
                    $genre = new Genre();
                    $genre->setName($currentGenre);
                    $em->persist($genre);

                    $show->addGenre($genre);
                } else $show->addGenre($genre);
            }

            $em->persist($show);

            $seasonIndex = 1;
            foreach ($showApi->_embedded->seasons as $currentSeason) {
                $season = new Season();

                $season->setNumber($currentSeason->number);

                $seasonPoster = '';
                if (!is_null($currentSeason->image)) $seasonPoster = $currentSeason->image->original;
                $season->setPoster($seasonPoster);

                $seasonEpisodeCount = 0;
                if (!is_null($currentSeason->episodeOrder)) $seasonEpisodeCount = $currentSeason->episodeOrder;
                $season->setEpisodeCount($seasonEpisodeCount);

                $seasonStartDate = new \DateTime($currentSeason->premiereDate);
                $season->setPremiereDate($seasonStartDate);

                $seasonEndDate = new \DateTime($currentSeason->endDate);
                $season->setEndDate($seasonEndDate);

                $season->setTvShow($show);

                $em->persist($season);

                foreach ($showApi->_embedded->episodes as $currentEpisode) {
                    if ($currentEpisode->season == $seasonIndex) {
                        $episode = new Episode();

                        $episode->setName($currentEpisode->name);
                        $episode->setNumber($currentEpisode->number);
                        $episode->setRuntime($currentEpisode->runtime);

                        $episodeSummary = '';
                        if (!is_null($currentEpisode->summary)) $episodeSummary = $currentEpisode->summary;
                        $episode->setSummary($episodeSummary);

                        $episodeAirstamp = new \DateTime($currentEpisode->airstamp);
                        $episode->setAirstamp($episodeAirstamp);

                        $episodeImage = '';
                        if (!is_null($currentEpisode->image)) $episodeImage = $currentEpisode->image->original;
                        $episode->setImage($episodeImage);

                        $episode->setSeason($season);

                        $em->persist($episode);
                    }
                }

                $seasonIndex++;
            }

            $em->flush();
        }

        $following = new Following();

        $following->setStartDate(new \DateTime());
        $following->setStatus($status);

        $user = $userRepository->find($id);
        $following->setUser($user);

        $following->setTvShow($show);

        $seasonFollow = $seasonRepository->findSeasonByShow($show, $seasonNumber);
        $following->setSeason($seasonFollow);
        
        $episodeFollow = $episodeRepository->findEpisodeBySeason($seasonFollow, $episodeNumber);
        $following->setEpisode($episodeFollow);

        $em->persist($following);
        $em->flush();

        $jsonResponse = new JsonResponse(['response' => 'success']);
        
        return $jsonResponse;
    }
}
