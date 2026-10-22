<?php

namespace Wexample\SymfonyTesting\Tests\Fixtures\App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Wexample\SymfonyTesting\Tests\Fixtures\App\Scenario\NoteStore;

/**
 * Members write notes, each one reads only their own, the administrator
 * reviews them all.
 */
class NotesController extends AbstractController
{
    #[Route('/login', name: 'login')]
    public function login(): Response
    {
        return new Response('<h1>Login</h1>');
    }

    #[Route('/', name: 'home')]
    public function home(): Response
    {
        return new Response('<h1>Home</h1>');
    }

    #[Route('/whoami', name: 'whoami')]
    public function whoami(): Response
    {
        return new Response($this->getUser()?->getUserIdentifier() ?? 'anonymous');
    }

    #[Route('/notes/new', name: 'note_new')]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $note = NoteStore::add((string) $request->request->get('title'), $this->getUser()->getUserIdentifier());

            return $this->redirectToRoute('note_show', ['id' => $note['id']]);
        }

        return new Response('<form method="post"><input name="title"><button>Write</button></form>');
    }

    #[Route('/notes/{id}', name: 'note_show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $note = NoteStore::get($id);

        if (! $note || ($note['author'] !== $this->getUser()->getUserIdentifier() && ! $this->isGranted('ROLE_ADMIN'))) {
            throw $this->createNotFoundException();
        }

        return new Response('<h1>' . $note['title'] . '</h1><p>' . $note['status'] . '</p>');
    }

    #[Route('/admin/notes/{id}', name: 'note_review', requirements: ['id' => '\d+'])]
    public function review(Request $request, int $id): Response
    {
        if (! NoteStore::get($id)) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            NoteStore::setStatus($id, $request->request->has('approve') ? 'approved' : 'rejected');

            return $this->redirectToRoute('note_show', ['id' => $id]);
        }

        return new Response('<form method="post"><button name="approve">Approve</button><button name="reject">Reject</button></form>');
    }
}
