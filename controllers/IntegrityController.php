<?php

require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/View.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../services/IntegrityService.php';
require_once __DIR__ . '/../utils/Helpers.php';

class IntegrityController
{
    private IntegrityService $integrityService;
    private Session $session;

    public function __construct()
    {
        $this->integrityService = new IntegrityService();
        $this->session          = new Session();
    }

    /**
     * Integrity dashboard page.
     */
    public function index(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $report = $this->integrityService->getIntegrityReport($userId);
            $score  = $this->integrityService->getIntegrityScore($userId);
            $flags  = $this->integrityService->getFlags($userId);

            return View::render('integrity/index', [
                'title'  => 'Academic Integrity',
                'report' => $report,
                'score'  => $score,
                'flags'  => $flags,
                'csrf'   => $this->session->csrf_token(),
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Unable to load integrity data.');
            return View::render('integrity/index', [
                'title'  => 'Academic Integrity',
                'report' => [],
                'score'  => [],
                'flags'  => [],
                'csrf'   => $this->session->csrf_token(),
            ], 'layouts/main');
        }
    }

    /**
     * Check writing integrity.
     */
    public function check(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $userId    = $this->session->get('user_id');
        $writingId = $params['id'] ?? '';

        if (empty($writingId)) {
            if ($request->isAjax()) {
                return Response::json(['success' => false, 'message' => 'Writing ID is required.'], 400);
            }
            $this->session->flash('error', 'Writing not found.');
            return redirect(url('/integrity'));
        }

        try {
            $result = $this->integrityService->checkWritingIntegrity($writingId);

            $this->integrityService->logIntegrityEvent($userId, 'writing_check', [
                'writing_id' => $writingId,
                'result'     => $result,
            ]);

            if ($request->isAjax()) {
                return Response::json([
                    'success' => true,
                    'result'  => $result,
                ]);
            }

            $this->session->flash('success', 'Integrity check complete.');
            return redirect(url('/integrity'));
        } catch (\RuntimeException $e) {
            if ($request->isAjax()) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            $this->session->flash('error', $e->getMessage());
            return redirect(url('/integrity'));
        }
    }

    /**
     * AJAX: Get integrity report.
     */
    public function getReport(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $report   = $this->integrityService->getIntegrityReport($userId);
            $behavior = $this->integrityService->getWritingBehavior($userId);

            return Response::json([
                'success'  => true,
                'report'   => $report,
                'behavior' => $behavior,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to load integrity report.',
            ], 500);
        }
    }

    /**
     * Create or view the integrity pledge.
     */
    public function pledge(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        if ($request->method() === 'POST') {
            Middleware::csrf();

            try {
                $pledge = $this->integrityService->generateIntegrityPledge($userId);

                $this->integrityService->logIntegrityEvent($userId, 'pledge_signed', [
                    'signed_at' => date('c'),
                ]);

                if ($request->isAjax()) {
                    return Response::json([
                        'success' => true,
                        'message' => 'Integrity pledge signed!',
                        'pledge'  => $pledge,
                    ]);
                }

                $this->session->flash('success', 'Integrity pledge signed! Thank you for your commitment.');
                return redirect(url('/integrity'));
            } catch (\RuntimeException $e) {
                if ($request->isAjax()) {
                    return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
                }
                $this->session->flash('error', $e->getMessage());
                return redirect(url('/integrity'));
            }
        }

        // GET: View pledge page
        try {
            $pledge = $this->integrityService->generateIntegrityPledge($userId);

            return View::render('integrity/pledge', [
                'title'  => 'Integrity Pledge',
                'pledge' => $pledge,
                'csrf'   => $this->session->csrf_token(),
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Unable to load pledge.');
            return redirect(url('/integrity'));
        }
    }

    /**
     * AJAX: Get integrity flags.
     */
    public function getFlags(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $flags = $this->integrityService->getFlags($userId);

            return Response::json([
                'success' => true,
                'flags'   => $flags,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to load flags.',
            ], 500);
        }
    }

    /**
     * AJAX: Get integrity score.
     */
    public function getScore(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $score    = $this->integrityService->getIntegrityScore($userId);
            $behavior = $this->integrityService->getWritingBehavior($userId);

            return Response::json([
                'success'  => true,
                'score'    => $score,
                'behavior' => $behavior,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to load integrity score.',
            ], 500);
        }
    }

    /**
     * AJAX: Get overall integrity statistics.
     */
    public function getStats(Request $request, array $params = []): string
    {
        Middleware::auth();

        try {
            $stats = $this->integrityService->getIntegrityStats();

            return Response::json([
                'success' => true,
                'stats'   => $stats,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to load integrity statistics.',
            ], 500);
        }
    }
}
