<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\PurchaseRequisition;
use App\Models\PurchaseRequisitionCategory;
use App\Models\PurchaseRequisitionItem;
use App\Models\PurchaseRequisitionOutletBudget;
use App\Models\Outlet;
use App\Models\User;
use App\Services\BudgetCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class PurchaseRequisitionBudgetTest extends TestCase
{
    use RefreshDatabase;

    protected $budgetService;
    protected $category;
    protected $outlet;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->budgetService = new BudgetCalculationService();
        
        // Create test user
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        
        // Create test category with GLOBAL budget
        $this->category = PurchaseRequisitionCategory::factory()->create([
            'budget_type' => 'GLOBAL',
            'budget_limit' => 10000000, // Rp 10 juta
        ]);
        
        // Create test outlet
        $this->outlet = Outlet::factory()->create();
    }

    /**
     * Test that PR Total excludes REJECTED status
     */
    public function test_pr_total_excludes_rejected_status()
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;
        $dateFrom = date('Y-m-01');
        $dateTo = date('Y-m-t');

        // Create PR with APPROVED status
        $approvedPR = PurchaseRequisition::factory()->create([
            'category_id' => $this->category->id,
            'status' => 'APPROVED',
            'is_held' => false,
            'created_at' => now(),
        ]);

        PurchaseRequisitionItem::factory()->create([
            'purchase_requisition_id' => $approvedPR->id,
            'category_id' => $this->category->id,
            'qty' => 1,
            'unit_price' => 1000000, // Rp 1 juta
            'subtotal' => 1000000,
            'created_at' => now(),
        ]);

        // Create PR with REJECTED status
        $rejectedPR = PurchaseRequisition::factory()->create([
            'category_id' => $this->category->id,
            'status' => 'REJECTED',
            'is_held' => false,
            'created_at' => now(),
        ]);

        PurchaseRequisitionItem::factory()->create([
            'purchase_requisition_id' => $rejectedPR->id,
            'category_id' => $this->category->id,
            'qty' => 1,
            'unit_price' => 5000000, // Rp 5 juta
            'subtotal' => 5000000,
            'created_at' => now(),
        ]);

        // Get budget info
        $budgetInfo = $this->budgetService->getBudgetInfo(
            $this->category->id,
            null,
            $dateFrom,
            $dateTo,
            0
        );

        // PR Total should only include APPROVED PR (1 juta), not REJECTED PR (5 juta)
        $this->assertTrue($budgetInfo['success']);
        $this->assertEquals(1000000, $budgetInfo['breakdown']['pr_total'], 'PR Total should exclude REJECTED status');
    }

    /**
     * Test that PR Total excludes deleted PRs
     */
    public function test_pr_total_excludes_deleted_prs()
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;
        $dateFrom = date('Y-m-01');
        $dateTo = date('Y-m-t');

        // Create PR with APPROVED status
        $approvedPR = PurchaseRequisition::factory()->create([
            'category_id' => $this->category->id,
            'status' => 'APPROVED',
            'is_held' => false,
            'created_at' => now(),
        ]);

        PurchaseRequisitionItem::factory()->create([
            'purchase_requisition_id' => $approvedPR->id,
            'category_id' => $this->category->id,
            'qty' => 1,
            'unit_price' => 1000000, // Rp 1 juta
            'subtotal' => 1000000,
            'created_at' => now(),
        ]);

        // Create PR and then delete it (soft delete)
        $deletedPR = PurchaseRequisition::factory()->create([
            'category_id' => $this->category->id,
            'status' => 'APPROVED',
            'is_held' => false,
            'created_at' => now(),
            'deleted_at' => now(),
        ]);

        PurchaseRequisitionItem::factory()->create([
            'purchase_requisition_id' => $deletedPR->id,
            'category_id' => $this->category->id,
            'qty' => 1,
            'unit_price' => 5000000, // Rp 5 juta
            'subtotal' => 5000000,
            'created_at' => now(),
        ]);

        // Get budget info
        $budgetInfo = $this->budgetService->getBudgetInfo(
            $this->category->id,
            null,
            $dateFrom,
            $dateTo,
            0
        );

        // PR Total should only include non-deleted PR (1 juta), not deleted PR (5 juta)
        $this->assertTrue($budgetInfo['success']);
        $this->assertEquals(1000000, $budgetInfo['breakdown']['pr_total'], 'PR Total should exclude deleted PRs');
    }

    /**
     * Test that PR Total excludes held PRs
     */
    public function test_pr_total_excludes_held_prs()
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;
        $dateFrom = date('Y-m-01');
        $dateTo = date('Y-m-t');

        // Create PR with APPROVED status (not held)
        $approvedPR = PurchaseRequisition::factory()->create([
            'category_id' => $this->category->id,
            'status' => 'APPROVED',
            'is_held' => false,
            'created_at' => now(),
        ]);

        PurchaseRequisitionItem::factory()->create([
            'purchase_requisition_id' => $approvedPR->id,
            'category_id' => $this->category->id,
            'qty' => 1,
            'unit_price' => 1000000, // Rp 1 juta
            'subtotal' => 1000000,
            'created_at' => now(),
        ]);

        // Create PR that is held
        $heldPR = PurchaseRequisition::factory()->create([
            'category_id' => $this->category->id,
            'status' => 'APPROVED',
            'is_held' => true,
            'created_at' => now(),
        ]);

        PurchaseRequisitionItem::factory()->create([
            'purchase_requisition_id' => $heldPR->id,
            'category_id' => $this->category->id,
            'qty' => 1,
            'unit_price' => 5000000, // Rp 5 juta
            'subtotal' => 5000000,
            'created_at' => now(),
        ]);

        // Get budget info
        $budgetInfo = $this->budgetService->getBudgetInfo(
            $this->category->id,
            null,
            $dateFrom,
            $dateTo,
            0
        );

        // PR Total should only include non-held PR (1 juta), not held PR (5 juta)
        $this->assertTrue($budgetInfo['success']);
        $this->assertEquals(1000000, $budgetInfo['breakdown']['pr_total'], 'PR Total should exclude held PRs');
    }

    /**
     * Test budget validation blocks when budget exceeded
     */
    public function test_budget_validation_blocks_when_exceeded()
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;
        $dateFrom = date('Y-m-01');
        $dateTo = date('Y-m-t');

        // Create PR that uses 8 juta from 10 juta budget
        $existingPR = PurchaseRequisition::factory()->create([
            'category_id' => $this->category->id,
            'status' => 'APPROVED',
            'is_held' => false,
            'created_at' => now(),
        ]);

        PurchaseRequisitionItem::factory()->create([
            'purchase_requisition_id' => $existingPR->id,
            'category_id' => $this->category->id,
            'qty' => 1,
            'unit_price' => 8000000, // Rp 8 juta
            'subtotal' => 8000000,
            'created_at' => now(),
        ]);

        // Try to validate new amount of 3 juta (total would be 11 juta, exceeds 10 juta)
        $validation = $this->budgetService->validateBudget(
            $this->category->id,
            null,
            3000000 // Rp 3 juta
        );

        $this->assertFalse($validation['valid'], 'Budget validation should fail when budget exceeded');
        $this->assertStringContainsString('exceeds', $validation['message'], 'Error message should mention budget exceeded');
    }

    /**
     * Test budget validation allows when budget sufficient
     */
    public function test_budget_validation_allows_when_sufficient()
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;
        $dateFrom = date('Y-m-01');
        $dateTo = date('Y-m-t');

        // Create PR that uses 5 juta from 10 juta budget
        $existingPR = PurchaseRequisition::factory()->create([
            'category_id' => $this->category->id,
            'status' => 'APPROVED',
            'is_held' => false,
            'created_at' => now(),
        ]);

        PurchaseRequisitionItem::factory()->create([
            'purchase_requisition_id' => $existingPR->id,
            'category_id' => $this->category->id,
            'qty' => 1,
            'unit_price' => 5000000, // Rp 5 juta
            'subtotal' => 5000000,
            'created_at' => now(),
        ]);

        // Try to validate new amount of 3 juta (total would be 8 juta, still within 10 juta)
        $validation = $this->budgetService->validateBudget(
            $this->category->id,
            null,
            3000000 // Rp 3 juta
        );

        $this->assertTrue($validation['valid'], 'Budget validation should pass when budget is sufficient');
    }
}

