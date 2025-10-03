<x-layouts.dashboard style="background-color: #f0f0f0;">
    <div>
        <!-- Datum -->
        <h2 class="text-lg font-semibold text-gray-700 mb-6 text-center md:text-left ">
            {{ \Carbon\Carbon::now()->locale('nl')->isoFormat('dd, DD.MM.YYYY') }}
        </h2>

        <!-- Stats cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-10 mt-10">
            <!-- Aantal actieve ploegen -->
            <div class="bg-white p-4 rounded-lg shadow text-center">
                <p class="text-md lg:text-xl text-gray-500 mb-2">Totaal aantal ploegen</p>
                <p class="text-2xl font-bold text-gray-800 mt-5">{{ $activeTeams }}</p>
            </div>

            <!-- Totaal aantal taken vandaag -->
            <div class="bg-white p-4 rounded-lg shadow text-center">
                <p class="text-md lg:text-xl text-gray-500 mb-2">Totaal aantal taken vandaag</p>
                <p class="text-2xl font-bold text-gray-800 mt-5">{{ $tasksToday }}</p>
            </div>

            <!-- Knoppen -->
            <div class="flex flex-col gap-3">
                <div class="bg-white p-4 rounded-lg shadow flex items-center justify-center">
                    <a href="{{ route('teams.index') }}" class=" rounded text-m font-medium">
                        <strong>Bekijk ploegen ></strong>
                    </a>
                </div>
                <div class="bg-white p-4 rounded-lg shadow flex items-center justify-center">
                    <a href="{{ route('schedule.index') }}" class=" rounded text-m font-medium">
                        <strong>Bekijk planning ></strong>
                    </a>
                </div>
            </div>
        </div>

        <!-- Chart -->
        <div class="bg-white p-6 rounded-lg shadow mt-8">
            <div class="flex flex-col justify-between items-center mb-4 md:flex-row">
                <h3 class="text-md font-bold text-center mb-4 md:text-left md:mb-0 md:text-xl">
                    Aantal voltooide taken vs. openstaand
                </h3>

                <!-- Periode select -->
                <div>
                    <label for="periodSelect" class="mr-2 font-medium">Periode:</label>
                    <select id="periodSelect" class="border px-3 py-2 rounded">
                        <option value="daily">Dagelijks</option>
                        <option value="weekly">Wekelijks</option>
                        <option value="monthly">Maandelijks</option>
                        <option value="yearly">Jaarlijks</option>
                    </select>
                </div>
            </div>

            <!-- vaste hoogte voor de chart -->
            <div style="height: 350px;">
                <canvas id="tasksChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('tasksChart');

        // Data uit Laravel (volledige periodes)
        const chartData = {
            daily: [{{ $tasksDailyFinished }}, {{ $tasksDailyOpen }}],
            weekly: [{{ $tasksWeeklyFinished }}, {{ $tasksWeeklyOpen }}],
            monthly: [{{ $tasksMonthlyFinished }}, {{ $tasksMonthlyOpen }}],
            yearly: [{{ $tasksYearlyFinished }}, {{ $tasksYearlyOpen }}],
        };

        // Init (standaard dagelijks)
        const tasksChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Voltooid', 'Openstaand'],
                datasets: [{
                    label: 'Taken',
                    data: chartData.daily,
                    backgroundColor: ['#10B981', '#F59E0B'],
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });

        // Switch tussen periodes
        document.getElementById('periodSelect').addEventListener('change', function () {
            const period = this.value;
            tasksChart.data.datasets[0].data = chartData[period] ?? [0, 0];
            tasksChart.update();
        });
    </script>
</x-layouts.dashboard>
