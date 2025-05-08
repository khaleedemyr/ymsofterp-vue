<?php

namespace App\Services;

use App\Repositories\DashboardRepository;

class DashboardService
{
    protected $dashboardRepository;

    public function __construct(DashboardRepository $dashboardRepository)
    {
        $this->dashboardRepository = $dashboardRepository;
    }

    public function getDashboardData($filters = [])
    {
        return [
            'stats' => $this->dashboardRepository->getStats($filters),
            'statusData' => $this->dashboardRepository->getStatusData($filters),
            'trendData' => $this->dashboardRepository->getTrendData($filters),
            'barOutletData' => $this->dashboardRepository->getBarOutletData($filters),
            'leaderboard' => $this->dashboardRepository->getLeaderboard($filters),
            'heatmap' => $this->dashboardRepository->getHeatmap($filters),
            'overdueTasks' => $this->dashboardRepository->getOverdueTasks($filters),
            'latestTasks' => $this->dashboardRepository->getLatestTasks($filters),
            'evidenceList' => $this->dashboardRepository->getEvidenceList($filters),
            'activityList' => $this->dashboardRepository->getActivityList($filters),
            'memberBarData' => $this->dashboardRepository->getMemberBarData($filters),
            'mediaGallery' => $this->dashboardRepository->getMediaGallery($filters),
            'poLatest' => $this->dashboardRepository->getPOLatest($filters),
            'categoryData' => $this->dashboardRepository->getCategoryData($filters),
            'priorityData' => $this->dashboardRepository->getPriorityData($filters),
        ];
    }

    public function getDashboardRepository()
    {
        return $this->dashboardRepository;
    }
} 