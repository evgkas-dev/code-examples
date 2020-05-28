<?php

namespace App\Http\Controllers;

use App\Models\TrainingBadUrl;
use App\Models\TrainingCategoryUrl;
use App\Models\TrainingParkedUrl;
use App\Services\TrainingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TrainingController extends Controller
{
    /**
     * @var TrainingService
     */
    private $trainingService;

    /**
     * Create a new controller instance.
     *
     * @param TrainingService $trainingService
     */
    public function __construct(TrainingService $trainingService)
    {
        $this->trainingService = $trainingService;
    }

    /**
     * Return URL info
     *
     * @param string $type
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function getUrlData($type, $id = null)
    {
        $response = new Collection([
            'success' => false
        ]);

        $id = (int)$id;

        $this->trainingService->setTrainingClass($type);

        $this->authorize('train', $this->trainingService->trainingClass);

        $urlModel = $this->trainingService->getUrlModel($id);

        if ($urlModel) {
            $response['id'] = (int)$urlModel->id;
            $response['url'] = $urlModel->url;

            $response['success'] = $this->trainingService->acquireUrl($urlModel->id, Auth::id());

            if ($response['success']) {
                try {
                    $response['metadata'] = $this->trainingService->getUrlMetaData($urlModel->url);
                } catch (\Throwable $e) {
                    $response['metadata'] = [];
                }
            }
        }

        return response()->json($response);
    }

    /**
     * @param Request $request
     * @param int $id
     * @param string $operation
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function train(Request $request, $id, $operation)
    {
        if (empty($id)) {
            throw new \Exception("URL ID required");
        }

        try {
            switch ($operation) {
                case "park":
                    $type = "park";
                    $param = TrainingParkedUrl::STATUS_PARKED;
                    break;

                case "unpark":
                    $type = "park";
                    $param = TrainingParkedUrl::STATUS_UNPARKED;
                    break;

                case "bad":
                    $type = "bad";
                    $param = TrainingBadUrl::STATUS_BAD;
                    break;

                case "good":
                    $type = "bad";
                    $param = TrainingBadUrl::STATUS_GOOD;
                    break;

                case "category":
                    $type = "category";

                    $param = $request->get('categoryId');
                    if (!array_key_exists($param, config('training.categories'))) {
                        throw new \Exception("Incorrect category ID");
                    }

                    break;

                default:
                    throw new \Exception("Unsupported type");

            }

            $this->trainingService->setTrainingClass($type);

            $this->authorize('train', $this->trainingService->trainingClass);

            $isSuccess = $this->trainingService->urlProcessing(Auth::id(), $id, $param);
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error($e);
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => $isSuccess,
            'type' => $type,
        ]);
    }

    public function categoryInit()
    {
        $this->authorize('train', TrainingCategoryUrl::class);

        return response()->json([
            'availableCategories' => config('training.categories')
        ]);
    }
}
