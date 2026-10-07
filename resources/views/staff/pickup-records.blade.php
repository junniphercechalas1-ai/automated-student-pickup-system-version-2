@extends('staff.layout')

@section('title', 'Scan History')

@section('content')
    @php
        $records = $records ?? collect();
    @endphp

    <section class="staff-history-page">
        <header class="staff-history-header">
            <div>
                <h2 class="staff-history-title">Scan History</h2>
                <p class="staff-history-subtitle">View all QR code verification records.</p>
            </div>

            <button id="pickupPrintReport" type="button" class="staff-history-export-button">
                <span class="staff-history-export-icon">⎙</span>
                <span>Print Report</span>
            </button>
        </header>
        <p id="pickupPrintStatus" class="staff-history-export-status" role="status" aria-live="polite"></p>

        <div class="staff-history-toolbar">
            <div class="staff-history-filter-block">
                <label>Date Range</label>
                <select id="pickupDateRange" class="staff-history-select" aria-label="Filter by date range">
                    <option value="all">All Dates</option>
                    <option value="today">Today</option>
                    <option value="last-7-days">Last 7 Days</option>
                    <option value="this-month">This Month</option>
                </select>
            </div>

            <div class="staff-history-filter-block staff-history-search-block">
                <label>Search</label>
                <div class="staff-history-search">
                    <input id="pickupSearch" type="search" class="staff-history-search-input" placeholder="Search name, ID, or guardian..." aria-label="Search scan history">
                    <button type="button" class="staff-history-search-icon" onclick="filterPickupRows()" aria-label="Search">⌕</button>
                </div>
            </div>
        </div>

        <div class="staff-history-stats">
            <div class="staff-history-stat-card staff-history-stat-card--blue">
                <div class="staff-history-stat-number">{{ $records->count() }}</div>
                <div class="staff-history-stat-label">Total Scans</div>
                <div class="staff-history-stat-meta">All verifications</div>
            </div>

            <div class="staff-history-stat-card staff-history-stat-card--green">
                <div class="staff-history-stat-number">{{ $records->where('result', 'Successful')->count() }}</div>
                <div class="staff-history-stat-label">Successful</div>
                <div class="staff-history-stat-meta">Entries authorized</div>
            </div>

            <div class="staff-history-stat-card staff-history-stat-card--red">
                <div class="staff-history-stat-number">{{ $records->where('result', 'Failed')->count() }}</div>
                <div class="staff-history-stat-label">Failed</div>
                <div class="staff-history-stat-meta">Verification failed</div>
            </div>

        </div>

        <div class="staff-history-table-wrap">
            <table class="staff-history-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Student</th>
                        <th>Parent / Guardian</th>
                        <th>Result</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @if($records->isNotEmpty())
                        @foreach($records as $record)
                            <tr data-date="{{ $record['picked_at'] ?? '' }}" data-date-label="{{ $record['date'] ?? '-' }}">
                                <td>{{ $record['time'] ?? '-' }}</td>
                                <td>{{ $record['student'] ?? '-' }}</td>
                                <td>{{ $record['guardian'] ?? '-' }}</td>
                                <td>
                                    <span class="staff-history-result staff-history-result--{{ strtolower($record['result'] ?? 'pending') }}">
                                        {{ ucfirst($record['result'] ?? 'Pending') }}
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="staff-history-action-button">View</button>
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="5" class="staff-history-empty-state">No verification records found.</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <div class="staff-history-pagination">
            <button id="pickupPreviousPage" type="button" class="staff-history-pagination-button is-disabled" aria-label="Previous page">‹</button>
            <button type="button" class="staff-history-pagination-button is-active" data-page="1">1</button>
            <button type="button" class="staff-history-pagination-button" data-page="2">2</button>
            <button type="button" class="staff-history-pagination-button" data-page="3">3</button>
            <button type="button" class="staff-history-pagination-button" data-page="4" aria-label="Page 4">...</button>
            <button type="button" class="staff-history-pagination-button" data-page="5">5</button>
            <button id="pickupNextPage" type="button" class="staff-history-pagination-button" aria-label="Next page">›</button>
        </div>

        <div class="staff-history-status-row">
            <div class="staff-history-system-status">
                <div class="staff-history-status-icon">✓</div>
                <div class="staff-history-status-copy">
                    <p class="staff-history-status-label">System Status: Online</p>
                    <p class="staff-history-status-text">Scanner is connected and ready.</p>
                </div>
            </div>

            <div class="staff-history-date-box">
                <div class="staff-history-date-item">
                    <span class="staff-history-date-icon">📅</span>
                    <span>{{ date('M d, Y') }}</span>
                </div>
                <div class="staff-history-date-item">
                    <span class="staff-history-date-icon">◔</span>
                    <span>{{ date('g:i A') }}</span>
                </div>
            </div>
        </div>
    </section>

    <footer class="staff-history-footer">© 2026 Orion Christian Academy. All rights reserved.</footer>

    <script>
        let pickupCurrentPage = 1;
        const pickupPageSize = 10;

        function getPickupRows() {
            return Array.from(document.querySelectorAll('.staff-history-table tbody tr[data-date]'));
        }

        function rowMatchesPickupFilters(row) {
            const term = document.getElementById('pickupSearch')?.value.trim().toLowerCase() ?? '';
            const range = document.getElementById('pickupDateRange')?.value ?? 'all';
            const now = new Date();
            const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            const startOfLastSevenDays = new Date(startOfToday);
            startOfLastSevenDays.setDate(startOfLastSevenDays.getDate() - 6);
            const rowDate = row.dataset.date ? new Date(row.dataset.date) : null;
            const rowDay = rowDate ? new Date(rowDate.getFullYear(), rowDate.getMonth(), rowDate.getDate()) : null;
            const matchesRange = range === 'all'
                || (range === 'today' && rowDay?.getTime() === startOfToday.getTime())
                || (range === 'last-7-days' && rowDay >= startOfLastSevenDays && rowDay <= startOfToday)
                || (range === 'this-month' && rowDay?.getFullYear() === now.getFullYear() && rowDay?.getMonth() === now.getMonth());

            return matchesRange && (!term || row.textContent.toLowerCase().includes(term));
        }

        function updatePickupPagination() {
            const rows = getPickupRows();
            const matchingRows = rows.filter(rowMatchesPickupFilters);
            const totalPages = Math.max(1, Math.ceil(matchingRows.length / pickupPageSize));
            pickupCurrentPage = Math.min(pickupCurrentPage, totalPages);

            rows.forEach((row) => {
                row.hidden = true;
            });

            const firstRowIndex = (pickupCurrentPage - 1) * pickupPageSize;
            matchingRows.slice(firstRowIndex, firstRowIndex + pickupPageSize).forEach((row) => {
                row.hidden = false;
            });

            document.querySelectorAll('.staff-history-pagination-button[data-page]').forEach((button) => {
                const page = Number(button.dataset.page);
                const isAvailable = page <= totalPages;
                button.classList.toggle('is-active', page === pickupCurrentPage);
                button.classList.toggle('is-disabled', !isAvailable);
                button.setAttribute('aria-current', page === pickupCurrentPage ? 'page' : 'false');
            });

            const previousButton = document.getElementById('pickupPreviousPage');
            const nextButton = document.getElementById('pickupNextPage');
            previousButton?.classList.toggle('is-disabled', pickupCurrentPage === 1);
            nextButton?.classList.toggle('is-disabled', pickupCurrentPage >= totalPages);
        }

        function filterPickupRows() {
            pickupCurrentPage = 1;
            updatePickupPagination();
        }

        function printPickupReport() {
            const matchingRows = getPickupRows().filter(rowMatchesPickupFilters);
            const status = document.getElementById('pickupPrintStatus');

            if (matchingRows.length === 0) {
                if (status) status.textContent = 'There are no scan records matching the current filters to print.';
                return;
            }

            const allRows = getPickupRows();
            allRows.forEach((row) => {
                row.hidden = !matchingRows.includes(row);
            });
            document.body.classList.add('printing-pickup-history');
            window.print();
        }

        document.getElementById('pickupPrintReport')?.addEventListener('click', printPickupReport);
        window.addEventListener('afterprint', () => {
            document.body.classList.remove('printing-pickup-history');
            updatePickupPagination();
        });
        document.querySelectorAll('.staff-history-pagination-button[data-page]').forEach((button) => {
            button.addEventListener('click', () => {
                if (button.classList.contains('is-disabled')) return;
                pickupCurrentPage = Number(button.dataset.page);
                updatePickupPagination();
            });
        });

        document.getElementById('pickupPreviousPage')?.addEventListener('click', () => {
            if (pickupCurrentPage > 1) {
                pickupCurrentPage -= 1;
                updatePickupPagination();
            }
        });

        document.getElementById('pickupNextPage')?.addEventListener('click', () => {
            const totalPages = Math.max(1, Math.ceil(getPickupRows().filter(rowMatchesPickupFilters).length / pickupPageSize));
            if (pickupCurrentPage < totalPages) {
                pickupCurrentPage += 1;
                updatePickupPagination();
            }
        });

        document.getElementById('pickupSearch')?.addEventListener('input', filterPickupRows);
        document.getElementById('pickupDateRange')?.addEventListener('change', filterPickupRows);
        updatePickupPagination();
    </script>
@endsection
