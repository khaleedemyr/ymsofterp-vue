<?php

namespace App\Http\Controllers;

use App\Services\Meta\MetaFacebookCommentsService;
use App\Services\Meta\MetaInstagramCommentsService;
use App\Support\OmnichannelAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class InstagramCommentsController extends Controller
{
    /**
     * Daftar akun IG & halaman FB untuk YMSoft App.
     */
    public function apiBootstrap(
        Request $request,
        MetaInstagramCommentsService $instagram,
        MetaFacebookCommentsService $facebook
    ): JsonResponse {
        $this->assertAccess($request);

        return response()->json([
            'success' => true,
            'data' => [
                'instagram_accounts' => $instagram->listAccounts(),
                'facebook_pages' => $facebook->listPages(),
            ],
        ]);
    }

    public function index(
        Request $request,
        MetaInstagramCommentsService $instagram,
        MetaFacebookCommentsService $facebook
    ): Response {
        $this->assertAccess($request);

        $platform = $request->get('platform', 'instagram');
        if (! in_array($platform, ['instagram', 'facebook'], true)) {
            $platform = 'instagram';
        }

        $igAccounts = $instagram->listAccounts();
        $fbPages = $facebook->listPages();

        $defaultAccount = $platform === 'facebook'
            ? (string) $request->get('account', $fbPages[0]['page_id'] ?? '')
            : (string) $request->get('account', $igAccounts[0]['ig_id'] ?? '');

        return Inertia::render('Crm/InstagramComments/Index', [
            'platform' => $platform,
            'instagramAccounts' => $igAccounts,
            'facebookPages' => $fbPages,
            'selectedAccount' => $defaultAccount,
            'initialPostId' => (string) $request->get('post', ''),
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

    public function facebookPosts(Request $request, string $pageId, MetaFacebookCommentsService $facebook): JsonResponse
    {
        $this->assertAccess($request);

        try {
            return response()->json([
                'media' => $facebook->listPosts($pageId, (int) $request->get('limit', 25)),
                'account' => $facebook->resolvePage($pageId),
            ]);
        } catch (RuntimeException $e) {
            return $this->jsonError($e);
        }
    }

    public function facebookComments(
        Request $request,
        string $pageId,
        string $postId,
        MetaFacebookCommentsService $facebook
    ): JsonResponse {
        $this->assertAccess($request);

        try {
            return response()->json([
                'comments' => $facebook->listComments($pageId, $postId, (int) $request->get('limit', 50)),
            ]);
        } catch (RuntimeException $e) {
            return $this->jsonError($e);
        }
    }

    public function facebookReply(
        Request $request,
        string $pageId,
        string $commentId,
        MetaFacebookCommentsService $facebook
    ): JsonResponse {
        $this->assertAccess($request);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:8000'],
        ]);

        try {
            $result = $facebook->replyToComment($pageId, $commentId, $validated['message']);

            return response()->json(['success' => true, 'result' => $result]);
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
