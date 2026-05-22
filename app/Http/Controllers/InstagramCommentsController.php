<?php

namespace App\Http\Controllers;

use App\Services\Meta\MetaInstagramCommentsService;
use App\Support\OmnichannelAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class InstagramCommentsController extends Controller
{
    public function index(Request $request, MetaInstagramCommentsService $comments): Response
    {
        $this->assertAccess($request);

        $accounts = $comments->listAccounts();
        $selectedIgId = (string) $request->get('account', $accounts[0]['ig_id'] ?? '');

        return Inertia::render('Crm/InstagramComments/Index', [
            'accounts' => $accounts,
            'selectedIgId' => $selectedIgId,
        ]);
    }

    public function media(Request $request, string $igAccount, MetaInstagramCommentsService $comments): JsonResponse
    {
        $this->assertAccess($request);

        try {
            $media = $comments->listMedia($igAccount, (int) $request->get('limit', 25));

            return response()->json([
                'media' => $media,
                'account' => $comments->resolveAccount($igAccount),
            ]);
        } catch (RuntimeException $e) {
            return $this->jsonError($e);
        }
    }

    public function comments(
        Request $request,
        string $igAccount,
        string $mediaId,
        MetaInstagramCommentsService $comments
    ): JsonResponse {
        $this->assertAccess($request);

        try {
            return response()->json([
                'comments' => $comments->listComments($igAccount, $mediaId, (int) $request->get('limit', 50)),
            ]);
        } catch (RuntimeException $e) {
            return $this->jsonError($e);
        }
    }

    public function reply(
        Request $request,
        string $igAccount,
        string $commentId,
        MetaInstagramCommentsService $comments
    ): JsonResponse {
        $this->assertAccess($request);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2200'],
        ]);

        try {
            $result = $comments->replyToComment($igAccount, $commentId, $validated['message']);

            return response()->json([
                'success' => true,
                'result' => $result,
            ]);
        } catch (RuntimeException $e) {
            return $this->jsonError($e);
        }
    }

    private function jsonError(RuntimeException $e): JsonResponse
    {
        $code = (int) $e->getCode();

        return response()->json(
            ['message' => $e->getMessage()],
            ($code >= 400 && $code < 600) ? $code : 422
        );
    }

    private function assertAccess(Request $request): void
    {
        abort_unless(OmnichannelAuthorization::canViewInbox($request->user()), 403);
    }
}
