<?php

namespace Happy\App\Api\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Happy\Storage\Models\Artifact;
use Happy\Storage\Models\Session;

/**
 * Artifact API controller - handles artifact CRUD operations.
 */
class ArtifactController
{
    /**
     * Get single artifact.
     * GET /v1/artifacts/{id}
     */
    public function get(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $artifactId = $args['id'];

        $artifact = Artifact::where('id', $artifactId)
            ->where('account_id', $user->id)
            ->first();

        if (!$artifact) {
            $response->getBody()->write(json_encode(['error' => 'Artifact not found']));
            return $response
                ->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode($this->formatArtifact($artifact)));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Create artifact.
     * POST /v1/artifacts
     */
    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['sessionId'])) {
            $response->getBody()->write(json_encode(['error' => 'sessionId is required']));
            return $response
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        // Verify session belongs to user
        $session = Session::where('id', $data['sessionId'])
            ->where('account_id', $user->id)
            ->first();

        if (!$session) {
            $response->getBody()->write(json_encode(['error' => 'Session not found']));
            return $response
                ->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }

        $artifact = new Artifact([
            'session_id' => $data['sessionId'],
            'account_id' => $user->id,
        ]);

        if (isset($data['content'])) {
            $artifact->setContent($data['content']);
        }
        if (isset($data['metadata'])) {
            $artifact->setMetadata($data['metadata']);
        }

        $artifact->save();

        $response->getBody()->write(json_encode($this->formatArtifact($artifact)));
        return $response
            ->withStatus(201)
            ->withHeader('Content-Type', 'application/json');
    }

    /**
     * Update artifact.
     * PUT /v1/artifacts/{id}
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $artifactId = $args['id'];
        $data = $request->getParsedBody() ?? [];

        $artifact = Artifact::where('id', $artifactId)
            ->where('account_id', $user->id)
            ->first();

        if (!$artifact) {
            $response->getBody()->write(json_encode(['error' => 'Artifact not found']));
            return $response
                ->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }

        // Version check for optimistic locking
        if (isset($data['version']) && $data['version'] !== $artifact->version) {
            $response->getBody()->write(json_encode(['error' => 'Version mismatch']));
            return $response
                ->withStatus(409)
                ->withHeader('Content-Type', 'application/json');
        }

        if (isset($data['content'])) {
            $artifact->setContent($data['content']);
        }
        if (isset($data['metadata'])) {
            $artifact->setMetadata($data['metadata']);
        }

        $artifact->save();

        $response->getBody()->write(json_encode($this->formatArtifact($artifact)));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Delete artifact.
     * DELETE /v1/artifacts/{id}
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $artifactId = $args['id'];

        $artifact = Artifact::where('id', $artifactId)
            ->where('account_id', $user->id)
            ->first();

        if (!$artifact) {
            $response->getBody()->write(json_encode(['error' => 'Artifact not found']));
            return $response
                ->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }

        $artifact->delete();

        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * List artifacts by session.
     * GET /v1/sessions/{sessionId}/artifacts
     */
    public function listBySession(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $sessionId = $args['sessionId'];

        // Verify session belongs to user
        $session = Session::where('id', $sessionId)
            ->where('account_id', $user->id)
            ->first();

        if (!$session) {
            $response->getBody()->write(json_encode(['error' => 'Session not found']));
            return $response
                ->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }

        $artifacts = Artifact::where('session_id', $sessionId)
            ->orderBy('created_at', 'desc')
            ->get();

        $result = [
            'artifacts' => $artifacts->map(fn($a) => $this->formatArtifact($a))->toArray(),
        ];

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function formatArtifact(Artifact $artifact): array
    {
        return [
            'id' => $artifact->id,
            'sessionId' => $artifact->session_id,
            'accountId' => $artifact->account_id,
            'content' => $artifact->getContent(),
            'metadata' => $artifact->getMetadata(),
            'version' => $artifact->version,
            'createdAt' => $artifact->created_at?->toIso8601String(),
            'updatedAt' => $artifact->updated_at?->toIso8601String(),
        ];
    }
}
