<?php
/**
 * StudyFlow - Create Note
 */
$noteColors = defined('NOTE_COLORS') ? NOTE_COLORS : [];
$subjects = [];
if (file_exists(DATA_PATH . '/subjects/subjects.json')) {
    $data = json_decode(file_get_contents(DATA_PATH . '/subjects/subjects.json'), true);
    $subjects = $data['subjects'] ?? [];
}
?>

<div class="max-w-2xl mx-auto">
    <nav class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-6">
        <a href="<?= url('/notes') ?>" class="hover:text-primary-600 transition">Notes</a>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-gray-900 dark:text-white font-medium">New Note</span>
    </nav>

    <div class="card animate-fade-in">
        <div class="card-header"><h1 class="text-xl font-bold text-gray-900 dark:text-white">📝 Create Note</h1></div>
        <div class="card-body">
            <form action="<?= url('/notes/save') ?>" method="POST" class="space-y-5">
                <?= csrf_field() ?>
                <div>
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-input" placeholder="Note title..." required>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Subject</label>
                        <select name="subject_id" class="form-select">
                            <option value="">General</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?= htmlspecialchars($s['id']) ?>"><?= htmlspecialchars($s['icon'] . ' ' . $s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Color</label>
                        <div class="flex gap-2 mt-1">
                            <?php foreach ($noteColors as $nc): ?>
                                <label class="cursor-pointer">
                                    <input type="radio" name="color" value="<?= htmlspecialchars($nc['id']) ?>" class="hidden peer" <?= $nc['id'] === 'yellow' ? 'checked' : '' ?>>
                                    <span class="block w-8 h-8 rounded-full border-2 border-transparent peer-checked:border-primary-500 peer-checked:ring-2 peer-checked:ring-primary-200 transition" style="background:<?= htmlspecialchars($nc['hex']) ?>"></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="form-label">Content</label>
                    <textarea name="content" class="form-textarea" rows="10" placeholder="Write your notes here..." data-auto-resize required></textarea>
                </div>
                <div>
                    <label class="form-label">Tags</label>
                    <input type="text" name="tags" class="form-input" placeholder="comma-separated tags">
                </div>
                <div class="flex justify-end gap-3">
                    <a href="<?= url('/notes') ?>" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Note</button>
                </div>
            </form>
        </div>
    </div>
</div>
