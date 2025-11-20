<?php

namespace Happy\App\Api\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Happy\Storage\Database;
use Happy\Storage\Models\Session;
use Happy\Storage\Models\SessionMessage;

/**
 * Session API controller - handles session CRUD operations.
 */
class SessionController
{
    /**
     * List user sessions with pagination.
     * GET /v1/sessions
     */
    public function list(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $params = $request->getQueryParams();

        $cursor = $params['cursor'] ?? null;
        $limit = min((int)($params['limit'] ?? 50), 100);

        $query = Session::where('account_id', $user->id)
            ->orderBy('sort_key', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($limit + 1);

        if ($cursor) {
            $query->where('created_at', '<', $cursor);
        }

        $sessions = $query->get();

        $hasMore = $sessions->count() > $limit;
        if ($hasMore) {
            $sessions->pop();
        }

        $result = [
            'sessions' => $sessions->map(fn($s) => $this->formatSession($s))->toArray(),
            'nextCursor' => $hasMore ? $sessions->last()->created_at->toIso8601String() : null,
        ];

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Create or get session by tag (idempotent).
     * POST /v1/sessions
     */
    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody() ?? [];

        $tag = $data['tag'] ?? null;

        // If tag provided, try to find existing session
        if ($tag) {
            $existing = Session::where('account_id', $user->id)
                ->where('tag', $tag)
                ->first();

            if ($existing) {
                $response->getBody()->write(json_encode($this->formatSession($existing)));
                return $response->withHeader('Content-Type', 'application/json');
            }
        }

        // Create new session
        $session = new Session([
            'account_id' => $user->id,
            'tag' => $tag,
            'sort_key' => (int)(microtime(true) * 1000),
        ]);

        if (isset($data['metadata'])) {
            $session->setMetadata($data['metadata']);
        }
        if (isset($data['state'])) {
            $session->setState($data['state']);
        }
        if (isset($data['agentState'])) {
            $session->setAgentState($data['agentState']);
        }

        $session->save();

        $response->getBody()->write(json_encode($this->formatSession($session)));
        return $response
            ->withStatus(201)
            ->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get single session.
     * GET /v1/sessions/{id}
     */
    public function get(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $sessionId = $args['id'];

        $session = Session::where('id', $sessionId)
            ->where('account_id', $user->id)
            ->first();

        if (!$session) {
            return $this->notFound($response, 'Session not found');
        }

        $response->getBody()->write(json_encode($this->formatSession($session)));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Update session.
     * PUT /v1/sessions/{id}
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $sessionId = $args['id'];
        $data = $request->getParsedBody() ?? [];

        $session = Session::where('id', $sessionId)
            ->where('account_id', $user->id)
            ->first();

        if (!$session) {
            return $this->notFound($response, 'Session not found');
        }

        // Version check for optimistic locking
        if (isset($data['version']) && $data['version'] !== $session->version) {
            return $this->conflict($response, 'Version mismatch');
        }

        if (isset($data['metadata'])) {
            $session->setMetadata($data['metadata']);
        }
        if (isset($data['state'])) {
            $session->setState($data['state']);
        }
        if (isset($data['agentState'])) {
            $session->setAgentState($data['agentState']);
        }
        if (isset($data['sortKey'])) {
            $session->sort_key = $data['sortKey'];
        }

        $session->save();

        $response->getBody()->write(json_encode($this->formatSession($session)));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Delete session with all related data.
     * DELETE /v1/sessions/{id}
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $sessionId = $args['id'];

        $session = Session::where('id', $sessionId)
            ->where('account_id', $user->id)
            ->first();

        if (!$session) {
            return $this->notFound($response, 'Session not found');
        }

        Database::transaction(function () use ($session) {
            $session->deleteWithRelations();
        });

        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get session messages with pagination.
     * GET /v1/sessions/{id}/messages
     */
    public function getMessages(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $sessionId = $args['id'];
        $params = $request->getQueryParams();

        $session = Session::where('id', $sessionId)
            ->where('account_id', $user->id)
            ->first();

        if (!$session) {
            return $this->notFound($response, 'Session not found');
        }

        $cursor = $params['cursor'] ?? null;
        $limit = min((int)($params['limit'] ?? 50), 100);

        $query = SessionMessage::where('session_id', $sessionId)
            ->orderBy('seq', 'desc')
            ->limit($limit + 1);

        if ($cursor) {
            $query->where('seq', '<', (int)$cursor);
        }

        $messages = $query->get();

        $hasMore = $messages->count() > $limit;
        if ($hasMore) {
            $messages->pop();
        }

        $result = [
            'messages' => $messages->map(fn($m) => $this->formatMessage($m))->toArray(),
            'nextCursor' => $hasMore ? (string)$messages->last()->seq : null,
        ];

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Create a message in a session.
     * POST /v1/sessions/{id}/messages
     */
    public function createMessage(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $sessionId = $args['id'];
        $data = $request->getParsedBody() ?? [];

        $session = Session::where('id', $sessionId)
            ->where('account_id', $user->id)
            ->first();

        if (!$session) {
            return $this->notFound($response, 'Session not found');
        }

        // Get next sequence number
        $maxSeq = SessionMessage::where('session_id', $sessionId)->max('seq') ?? 0;

        $message = new SessionMessage([
            'session_id' => $sessionId,
            'local_id' => $data['localId'] ?? null,
            'seq' => $maxSeq + 1,
        ]);

        if (isset($data['content'])) {
            $message->setContent($data['content']);
        }

        $message->save();

        $response->getBody()->write(json_encode($this->formatMessage($message)));
        return $response
            ->withStatus(201)
            ->withHeader('Content-Type', 'application/json');
    }

    private function formatSession(Session $session): array
    {
        return [
            'id' => $session->id,
            'accountId' => $session->account_id,
            'tag' => $session->tag,
            'metadata' => $session->getMetadata(),
            'state' => $session->getState(),
            'agentState' => $session->getAgentState(),
            'version' => $session->version,
            'sortKey' => $session->sort_key,
            'createdAt' => $session->created_at?->toIso8601String(),
            'updatedAt' => $session->updated_at?->toIso8601String(),
        ];
    }

    private function formatMessage(SessionMessage $message): array
    {
        return [
            'id' => $message->id,
            'sessionId' => $message->session_id,
            'localId' => $message->local_id,
            'content' => $message->getContent(),
            'seq' => $message->seq,
            'createdAt' => $message->created_at?->toIso8601String(),
        ];
    }

    private function notFound(Response $response, string $message): Response
    {
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
    }

    private function conflict(Response $response, string $message): Response
    {
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response
            ->withStatus(409)
            ->withHeader('Content-Type', 'application/json');
    }
}
