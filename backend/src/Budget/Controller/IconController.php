<?php

namespace App\Budget\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route('/api')]
class IconController extends AbstractController
{
    #[Route('/icons', name: 'api_icons_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/icons',
        summary: 'Get list of available icons',
        tags: ['Icons']
    )]
    #[OA\Response(
        response: 200,
        description: 'List of available icon filenames',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(type: 'string', example: 'acorn-thin.svg')
        )
    )]
    public function listIcons(): JsonResponse
    {
        $iconsDir = $this->getParameter('kernel.project_dir') . '/public/icons';

        if (!is_dir($iconsDir)) {
            return $this->json([]);
        }

        $files = scandir($iconsDir);
        $icons = array_values(array_filter($files, function($file) use ($iconsDir) {
            return is_file($iconsDir . '/' . $file) && str_ends_with($file, '.svg');
        }));

        sort($icons);

        $response = $this->json($icons);
        $response->headers->set('Cache-Control', 'public, max-age=86400'); // Cache for 24 hours
        return $response;
    }

    #[Route('/icons/{filename}', name: 'api_icon_serve', methods: ['GET'])]
    #[OA\Get(
        path: '/api/icons/{filename}',
        summary: 'Serve a specific icon file',
        tags: ['Icons']
    )]
    #[OA\Parameter(
        name: 'filename',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', example: 'acorn-thin.svg')
    )]
    #[OA\Response(
        response: 200,
        description: 'SVG icon file',
        content: new OA\MediaType(mediaType: 'image/svg+xml')
    )]
    #[OA\Response(
        response: 404,
        description: 'Icon not found'
    )]
    public function serveIcon(string $filename): Response
    {
        // Security: only allow SVG files and prevent directory traversal
        if (!str_ends_with($filename, '.svg') || str_contains($filename, '..') || str_contains($filename, '/')) {
            throw new NotFoundHttpException('Invalid filename');
        }

        $iconPath = $this->getParameter('kernel.project_dir') . '/public/icons/' . $filename;

        if (!file_exists($iconPath) || !is_file($iconPath)) {
            throw new NotFoundHttpException('Icon not found');
        }

        $response = new Response(file_get_contents($iconPath));
        $response->headers->set('Content-Type', 'image/svg+xml');
        $response->headers->set('Cache-Control', 'public, max-age=31536000'); // Cache for 1 year

        return $response;
    }
}
