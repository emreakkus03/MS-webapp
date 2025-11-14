<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Mail\RepairTasksMail;
use App\Models\Task;
use Carbon\Carbon;

class SendRepairTasksMail extends Command
{
    protected $signature = 'mail:repair-tasks';
    protected $description = 'Send daily mail with all tasks that need repair for today and late ones from yesterday.';

    public function handle()
    {
        $now = Carbon::now('Europe/Brussels');
        $yesterdayAtSix = $now->copy()->subDay()->setTime(17, 0, 0);

        $this->info('📅 Collecting repair tasks since ' . $yesterdayAtSix->toDateTimeString());

        $tasks = Task::with('address')
            ->whereNotNull('note')
            ->where(function ($query) use ($now, $yesterdayAtSix) {
                $query
                    ->whereDate('time', $now->toDateString())
                    ->orWhere(function ($q) use ($yesterdayAtSix, $now) {
                        $q->whereDate('time', $yesterdayAtSix->toDateString())
                            ->where('updated_at', '>=', $yesterdayAtSix)
                            ->where('updated_at', '<', $now);
                    });
            })
            ->get();

        if ($tasks->isEmpty()) {
            $this->info('No repair tasks found for this period.');
            return Command::SUCCESS;
        }

        // 🔹 Ontvangers
        $toRecipients = [
            'can.coskun@msinfra.be',
            'hasan.bagator@msinfra.be'
        ];

        // 🔹 CC (kopie)
        $ccRecipients = [
            'Marco.pieters@msinfra.be',
            'selcuk.yilmaz@msinfra.be'
        ];

        $this->info('🛠️ Tasks found: ' . $tasks->count());
        $this->info('📤 Sending mail to: ' . implode(', ', $toRecipients));
        $this->info('📋 CC: ' . implode(', ', $ccRecipients));

        // ✅ Mail verzenden met meerdere ontvangers en CC
        Mail::to($toRecipients)
            ->cc($ccRecipients)
            ->send(new RepairTasksMail(
                $tasks,
                $yesterdayAtSix->format('d/m H:i'),
                $now->format('d/m H:i')
            ));

        $this->info('✅ Daily repair tasks mail sent successfully.');
        return Command::SUCCESS;
    }
}
