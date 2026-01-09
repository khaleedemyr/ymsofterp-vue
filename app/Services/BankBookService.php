<?php

namespace App\Services;

use App\Models\BankBook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BankBookService
{
    /**
     * Create a bank book entry and recalculate balance
     *
     * @param array $data
     * @return BankBook
     * @throws \Exception
     */
    public function createEntry(array $data): BankBook
    {
        try {
            DB::beginTransaction();

            $bankBook = $this->createEntryWithoutTransaction($data);

            // Recalculate balance for all entries after this one (if there are any with same or later date)
            BankBook::recalculateBalance($data['bank_account_id'], $data['transaction_date']);

            DB::commit();

            return $bankBook;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('BankBookService::createEntry failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Create a bank book entry without transaction (for use within existing transaction)
     *
     * @param array $data
     * @return BankBook
     * @throws \Exception
     */
    private function createEntryWithoutTransaction(array $data): BankBook
    {
        // Validate required fields
        if (!isset($data['bank_account_id']) || !isset($data['transaction_date']) || 
            !isset($data['transaction_type']) || !isset($data['amount'])) {
            throw new \Exception('Missing required fields: bank_account_id, transaction_date, transaction_type, amount');
        }

        // Get last balance for this bank account
        $lastEntry = BankBook::where('bank_account_id', $data['bank_account_id'])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        $currentBalance = $lastEntry ? $lastEntry->balance : 0;

        // Calculate new balance
        if ($data['transaction_type'] === 'credit') {
            $newBalance = $currentBalance + $data['amount'];
        } else {
            $newBalance = $currentBalance - $data['amount'];
        }

        $data['balance'] = $newBalance;

        // Create entry
        return BankBook::create($data);
    }

    /**
     * Create bank book entry from outlet payment
     * For transfer/check: creates 2 entries (debit from sender bank, credit to receiver bank)
     *
     * @param \App\Models\OutletPayment $outletPayment
     * @return array|null Returns array of created BankBook entries or null
     */
    public function createFromOutletPayment($outletPayment): ?array
    {
        // Only create entry if payment method is transfer or check
        if (!in_array($outletPayment->payment_method, ['transfer', 'check'])) {
            return null;
        }

        // Only create if status is paid
        if ($outletPayment->status !== 'paid') {
            return null;
        }

        // Check if already exists in bank book to avoid duplicate
        $existingEntry = BankBook::where('reference_type', 'outlet_payment')
            ->where('reference_id', $outletPayment->id)
            ->first();
        
        if ($existingEntry) {
            // Already exists, return null to avoid duplicate
            return null;
        }

        try {
            $entries = [];

            // Entry 1: Debit from sender bank (outlet bank)
            if ($outletPayment->bank_id) {
                $debitData = [
                    'bank_account_id' => $outletPayment->bank_id,
                    'transaction_date' => $outletPayment->date,
                    'transaction_type' => 'debit', // Money going out from outlet bank
                    'amount' => $outletPayment->total_amount,
                    'description' => "Outlet Payment: {$outletPayment->payment_number} (Pengirim)" . 
                        ($outletPayment->notes ? " - {$outletPayment->notes}" : ''),
                    'reference_type' => 'outlet_payment',
                    'reference_id' => $outletPayment->id,
                ];

                $entries[] = $this->createEntryWithoutTransaction($debitData);
            }

            // Entry 2: Credit to receiver bank (head office bank)
            if ($outletPayment->receiver_bank_id) {
                $creditData = [
                    'bank_account_id' => $outletPayment->receiver_bank_id,
                    'transaction_date' => $outletPayment->date,
                    'transaction_type' => 'credit', // Money coming in to head office bank
                    'amount' => $outletPayment->total_amount,
                    'description' => "Outlet Payment: {$outletPayment->payment_number} (Penerima)" . 
                        ($outletPayment->notes ? " - {$outletPayment->notes}" : ''),
                    'reference_type' => 'outlet_payment',
                    'reference_id' => $outletPayment->id,
                ];

                $entries[] = $this->createEntryWithoutTransaction($creditData);
            }

            // Recalculate balance for both banks if entries were created
            if (!empty($entries)) {
                if ($outletPayment->bank_id) {
                    BankBook::recalculateBalance($outletPayment->bank_id, $outletPayment->date);
                }
                if ($outletPayment->receiver_bank_id) {
                    BankBook::recalculateBalance($outletPayment->receiver_bank_id, $outletPayment->date);
                }
            }

            return !empty($entries) ? $entries : null;
        } catch (\Exception $e) {
            Log::error('BankBookService::createFromOutletPayment failed', [
                'error' => $e->getMessage(),
                'outlet_payment_id' => $outletPayment->id,
            ]);
            return null;
        }
    }

    /**
     * Create bank book entry from food payment
     * Note: Food Payment only has bank_id (sender bank), no receiver_bank_id
     *
     * @param \App\Models\FoodPayment $foodPayment
     * @return BankBook|null
     */
    public function createFromFoodPayment($foodPayment): ?BankBook
    {
        // Only create entry if payment type is Transfer or Giro and has bank_id
        if (!in_array($foodPayment->payment_type, ['Transfer', 'Giro']) || !$foodPayment->bank_id) {
            return null;
        }

        // Only create if status is paid
        if ($foodPayment->status !== 'paid') {
            return null;
        }

        // Check if already exists in bank book to avoid duplicate
        $existingEntry = BankBook::where('reference_type', 'food_payment')
            ->where('reference_id', $foodPayment->id)
            ->first();
        
        if ($existingEntry) {
            // Already exists, return null to avoid duplicate
            return null;
        }

        try {
            $data = [
                'bank_account_id' => $foodPayment->bank_id,
                'transaction_date' => $foodPayment->date,
                'transaction_type' => 'debit', // Food payment is money going out from bank
                'amount' => $foodPayment->total,
                'description' => "Food Payment: {$foodPayment->number}" . 
                    ($foodPayment->notes ? " - {$foodPayment->notes}" : ''),
                'reference_type' => 'food_payment',
                'reference_id' => $foodPayment->id,
            ];

            $bankBook = $this->createEntryWithoutTransaction($data);
            
            // Recalculate balance for this bank account
            BankBook::recalculateBalance($foodPayment->bank_id, $foodPayment->date);

            return $bankBook;
        } catch (\Exception $e) {
            Log::error('BankBookService::createFromFoodPayment failed', [
                'error' => $e->getMessage(),
                'food_payment_id' => $foodPayment->id,
            ]);
            return null;
        }
    }

    /**
     * Create bank book entry from non food payment
     *
     * @param \App\Models\NonFoodPayment $nonFoodPayment
     * @return BankBook|null
     */
    public function createFromNonFoodPayment($nonFoodPayment): ?BankBook
    {
        // Only create entry if payment method is transfer or check and has bank_id
        if (!in_array($nonFoodPayment->payment_method, ['transfer', 'check']) || !$nonFoodPayment->bank_id) {
            return null;
        }

        // Only create if status is paid
        if ($nonFoodPayment->status !== 'paid') {
            return null;
        }

        // Check if already exists in bank book to avoid duplicate
        $existingEntry = BankBook::where('reference_type', 'non_food_payment')
            ->where('reference_id', $nonFoodPayment->id)
            ->first();
        
        if ($existingEntry) {
            // Already exists, return null to avoid duplicate
            return null;
        }

        try {
            $data = [
                'bank_account_id' => $nonFoodPayment->bank_id,
                'transaction_date' => $nonFoodPayment->payment_date,
                'transaction_type' => 'debit', // Non food payment is money going out
                'amount' => $nonFoodPayment->amount,
                'description' => "Non Food Payment: {$nonFoodPayment->payment_number}" . 
                    ($nonFoodPayment->description ? " - {$nonFoodPayment->description}" : ''),
                'reference_type' => 'non_food_payment',
                'reference_id' => $nonFoodPayment->id,
            ];

            $bankBook = $this->createEntryWithoutTransaction($data);
            
            // Recalculate balance for this bank account
            BankBook::recalculateBalance($nonFoodPayment->bank_id, $nonFoodPayment->payment_date);

            return $bankBook;
        } catch (\Exception $e) {
            Log::error('BankBookService::createFromNonFoodPayment failed', [
                'error' => $e->getMessage(),
                'non_food_payment_id' => $nonFoodPayment->id,
            ]);
            return null;
        }
    }

    /**
     * Update bank book entry and recalculate balance
     *
     * @param BankBook $bankBook
     * @param array $data
     * @return BankBook
     * @throws \Exception
     */
    public function updateEntry(BankBook $bankBook, array $data): BankBook
    {
        try {
            DB::beginTransaction();

            // Get the date before update to determine if we need to recalculate
            $oldDate = $bankBook->transaction_date;
            $oldBankAccountId = $bankBook->bank_account_id;

            $bankBook->update($data);

            // Recalculate balance if date or bank account changed
            $recalculateFromDate = min($oldDate, $data['transaction_date'] ?? $oldDate);

            if ($oldBankAccountId != ($data['bank_account_id'] ?? $oldBankAccountId)) {
                // If bank account changed, recalculate both
                BankBook::recalculateBalance($oldBankAccountId, $recalculateFromDate);
                BankBook::recalculateBalance($data['bank_account_id'] ?? $oldBankAccountId, $recalculateFromDate);
            } else {
                BankBook::recalculateBalance($oldBankAccountId, $recalculateFromDate);
            }

            DB::commit();

            return $bankBook->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('BankBookService::updateEntry failed', [
                'error' => $e->getMessage(),
                'bank_book_id' => $bankBook->id,
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Delete bank book entry and recalculate balance
     *
     * @param BankBook $bankBook
     * @return bool
     * @throws \Exception
     */
    public function deleteEntry(BankBook $bankBook): bool
    {
        try {
            DB::beginTransaction();

            $bankAccountId = $bankBook->bank_account_id;
            $transactionDate = $bankBook->transaction_date;

            $bankBook->delete();

            // Recalculate balance after deletion
            BankBook::recalculateBalance($bankAccountId, $transactionDate);

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('BankBookService::deleteEntry failed', [
                'error' => $e->getMessage(),
                'bank_book_id' => $bankBook->id,
            ]);
            throw $e;
        }
    }

    /**
     * Delete bank book entries by reference (when transaction is deleted)
     *
     * @param string $referenceType
     * @param int $referenceId
     * @return bool
     */
    public function deleteByReference(string $referenceType, int $referenceId): bool
    {
        try {
            // Find all entries with this reference
            $entries = BankBook::where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->get();

            if ($entries->isEmpty()) {
                return true; // No entries to delete
            }

            // Group by bank_account_id and transaction_date for recalculation
            $recalculateMap = [];
            foreach ($entries as $entry) {
                $key = $entry->bank_account_id . '_' . $entry->transaction_date->format('Y-m-d');
                if (!isset($recalculateMap[$key])) {
                    $recalculateMap[$key] = [
                        'bank_account_id' => $entry->bank_account_id,
                        'transaction_date' => $entry->transaction_date,
                    ];
                }
            }

            // Delete all entries
            BankBook::where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->delete();

            // Recalculate balance for affected banks
            foreach ($recalculateMap as $data) {
                BankBook::recalculateBalance($data['bank_account_id'], $data['transaction_date']);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('BankBookService::deleteByReference failed', [
                'error' => $e->getMessage(),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);
            return false;
        }
    }
}
