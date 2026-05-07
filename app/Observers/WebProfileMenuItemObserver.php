<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\WebProfileMenuItem;
use Illuminate\Support\Facades\Request;

class WebProfileMenuItemObserver
{
    /** @var array<string, array<string, mixed>> */
    private array $updateOriginals = [];

    /** @var array<string, array<string, mixed>> */
    private array $deleteSnapshots = [];

    public function created(WebProfileMenuItem $menuItem): void
    {
        $this->writeLog(
            'navbar_menu_created',
            'Navbar menu ditambah: '.$menuItem->label,
            null,
            $this->snapshot($menuItem)
        );
    }

    public function updating(WebProfileMenuItem $menuItem): void
    {
        $this->updateOriginals[spl_object_hash($menuItem)] = $menuItem->getOriginal();
    }

    public function updated(WebProfileMenuItem $menuItem): void
    {
        $hash = spl_object_hash($menuItem);
        $original = $this->updateOriginals[$hash] ?? [];
        unset($this->updateOriginals[$hash]);

        $changes = $menuItem->getChanges();
        unset($changes['updated_at'], $changes['created_at']);
        if ($changes === []) {
            return;
        }

        $old = array_intersect_key($original, $changes);
        $new = array_intersect_key($menuItem->getAttributes(), $changes);

        $this->writeLog(
            'navbar_menu_updated',
            'Navbar menu diperbarui: '.$menuItem->label,
            $old !== [] ? $old : null,
            $new !== [] ? $new : null
        );
    }

    public function deleting(WebProfileMenuItem $menuItem): void
    {
        $this->deleteSnapshots[spl_object_hash($menuItem)] = $this->snapshot($menuItem);
    }

    public function deleted(WebProfileMenuItem $menuItem): void
    {
        $hash = spl_object_hash($menuItem);
        $snap = $this->deleteSnapshots[$hash] ?? ['label' => 'unknown'];
        unset($this->deleteSnapshots[$hash]);

        $this->writeLog(
            'navbar_menu_deleted',
            'Navbar menu dihapus: '.($snap['label'] ?? '?'),
            $snap,
            null
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(WebProfileMenuItem $menuItem): array
    {
        return [
            'id' => $menuItem->id,
            'label' => $menuItem->label,
            'url' => $menuItem->url,
            'page_id' => $menuItem->page_id,
            'parent_id' => $menuItem->parent_id,
            'order' => $menuItem->order,
            'is_active' => $menuItem->is_active,
        ];
    }

    private function writeLog(string $activityType, string $description, ?array $oldData, ?array $newData): void
    {
        ActivityLog::create([
            'user_id' => auth()->id(),
            'activity_type' => $activityType,
            'module' => 'web_profile_security',
            'description' => $description,
            'ip_address' => Request::ip(),
            'user_agent' => (string) Request::header('User-Agent'),
            'old_data' => $oldData,
            'new_data' => $newData,
            'created_at' => now(),
        ]);
    }
}
