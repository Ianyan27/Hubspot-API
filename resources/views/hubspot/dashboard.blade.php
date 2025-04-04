<!-- resources/views/hubspot/dashboard.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HubSpot Sync Dashboard</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container py-4">
        <h1 class="mb-4">HubSpot Contact Sync Dashboard</h1>
        
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        
        @if(session('warning'))
            <div class="alert alert-warning">{{ session('warning') }}</div>
        @endif
        
        @if(session('info'))
            <div class="alert alert-info">{{ session('info') }}</div>
        @endif
        
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Total Contacts</h5>
                        <h2 id="total-contacts">{{ number_format($totalContacts) }}</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Sync Status</h5>
                        <h2 id="sync-status" class="text-capitalize">{{ $syncStatus->status }}</h2>
                        <div id="progress-container" class="mt-2 {{ $syncStatus->status !== 'running' ? 'd-none' : '' }}">
                            <div class="progress">
                                <div id="sync-progress" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Last Sync</h5>
                        <h2 id="last-sync">{{ $lastSyncDate ? $lastSyncDate->diffForHumans() : 'Never' }}</h2>
                        <p>{{ $lastSyncDate ? $lastSyncDate->format('Y-m-d H:i:s') : '-' }}</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card" style="height: 275px">
                    <div class="card-header">
                        <h5>Time Window Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <strong>Next Sync Window:</strong> The dates below represent the next time window for synchronization.
                        </div>
                        
                        <dl class="row">
                            <dt class="col-sm-4">Start Date (Greater Than)</dt>
                            <dd class="col-sm-8">
                                <span id="display-start-date">{{ Carbon\Carbon::parse($nextStartDate)->format('Y-m-d H:i:s') }}</span>
                            </dd>
                            
                            <dt class="col-sm-4">End Date (Less Than)</dt>
                            <dd class="col-sm-8">
                                <span id="display-end-date">{{ Carbon\Carbon::parse($endDate)->format('Y-m-d H:i:s') }}</span>
                            </dd>
                        </dl>
                    </div>
                </div>
                
                <div class="card mt-3" style="height: 350px;">
                    <div class="card-header">
                        <h5>Manually Trigger Sync</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('hubspot.sync') }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Start Date (Greater Than)</label>
                                <input type="datetime-local" class="form-control" id="start_date" name="start_date" 
                                    value="{{ Carbon\Carbon::parse($nextStartDate)->format('Y-m-d\TH:i') }}">
                                <div class="form-text">Default is the last sync timestamp</div>
                            </div>
                            <div class="mb-3">
                                <label for="end_date" class="form-label">End Date (Less Than)</label>
                                <input type="datetime-local" class="form-control" id="end_date" name="end_date"
                                    value="{{ Carbon\Carbon::now()->format('Y-m-d\TH:i') }}">
                                <div class="form-text">Default is current time</div>
                            </div>
                            <button type="submit" class="btn btn-primary" id="start-sync-btn" {{ $syncStatus->status === 'running' ? 'disabled' : '' }}>Sync Next Batch</button>
                            
                            @if($syncStatus->status === 'running')
                                <button type="button" class="btn btn-danger" id="cancel-sync-btn">Cancel Sync</button>
                            @endif
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card" style="height: 275px;">
                    <div class="card-header">
                        <h5>Sync Details</h5>
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-4">Current Window Start</dt>
                            <dd class="col-sm-8" id="window-start">{{ $syncStatus->start_window ? $syncStatus->start_window->format('Y-m-d H:i:s') : '-' }}</dd>
                            
                            <dt class="col-sm-4">Current Window End</dt>
                            <dd class="col-sm-8" id="window-end">{{ $syncStatus->end_window ? $syncStatus->end_window->format('Y-m-d H:i:s') : '-' }}</dd>
                            
                            <dt class="col-sm-4">Total Synced</dt>
                            <dd class="col-sm-8" id="total-synced">{{ number_format($syncStatus->total_synced) }}</dd>
                            
                            <dt class="col-sm-4">Total Errors</dt>
                            <dd class="col-sm-8" id="total-errors">{{ number_format($syncStatus->total_errors) }}</dd>
                            
                            <dt class="col-sm-4">Next Scheduled Sync</dt>
                            <dd class="col-sm-8" id="next-sync">{{ $syncStatus->next_sync_timestamp ? $syncStatus->next_sync_timestamp->format('Y-m-d H:i:s') : '-' }}</dd>
                        </dl>
                        
                        @if($syncStatus->error_log)
                            <div class="alert alert-danger mt-3">
                                <h6>Error Log</h6>
                                <pre>{{ $syncStatus->error_log }}</pre>
                            </div>
                        @endif
                    </div>
                </div>
                
                <div class="card mt-3" style="height: 350px">
                    <div class="card-header">
                        <h5>Batch Processing Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-secondary">
                            <p><strong>How it works:</strong></p>
                            <ul>
                                <li>Each sync retrieves contacts within the date range shown above</li>
                                <li>Contacts are processed in batches of 1,000</li>
                                <li>Partial batches are kept in buffer for the next sync</li>
                                <li>The end date of each successful sync becomes the start date for the next sync</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-end mb-4">
            <a href="{{ route('hubspot.history') }}" class="btn btn-secondary">View Sync History</a>
        </div>
        <div class="text-end mb-4">
            <a href="{{ route('hubspot.retrieval-history') }}" class="btn btn-info ms-2">View Retrieval History</a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Poll for status updates if sync is running
        let pollingInterval;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        function updateStatus() {
            fetch('{{ route("hubspot.status") }}')
                .then(response => response.json())
                .then(data => {
                    // Update status
                    document.getElementById('sync-status').textContent = data.status;
                    
                    // Update buffer count
                    document.getElementById('buffer-count').textContent = data.buffer_count;
                    
                    // Update progress elements
                    document.getElementById('total-synced').textContent = new Intl.NumberFormat().format(data.total_synced);
                    
                    // Show/hide progress bar
                    const progressContainer = document.getElementById('progress-container');
                    if (data.status === 'running') {
                        progressContainer.classList.remove('d-none');
                        document.getElementById('start-sync-btn').disabled = true;
                        if (!document.getElementById('cancel-sync-btn')) {
                            const cancelBtn = document.createElement('button');
                            cancelBtn.type = 'button';
                            cancelBtn.id = 'cancel-sync-btn';
                            cancelBtn.className = 'btn btn-danger ms-2';
                            cancelBtn.textContent = 'Cancel Sync';
                            cancelBtn.addEventListener('click', cancelSync);
                            document.getElementById('start-sync-btn').after(cancelBtn);
                        }
                    } else {
                        progressContainer.classList.add('d-none');
                        document.getElementById('start-sync-btn').disabled = false;
                        const cancelBtn = document.getElementById('cancel-sync-btn');
                        if (cancelBtn) {
                            cancelBtn.remove();
                        }
                        
                        // Stop polling if sync is not running
                        clearInterval(pollingInterval);
                    }
                    
                    // Update start date for next sync if available
                    if (data.last_sync) {
                        const lastSyncDate = new Date(data.last_sync);
                        const formattedDate = lastSyncDate.toISOString().slice(0, 16);
                        document.getElementById('start_date').value = formattedDate;
                        document.getElementById('display-start-date').textContent = lastSyncDate.toLocaleString();
                    }
                })
                .catch(error => console.error('Error fetching status:', error));
        }
        
        function cancelSync() {
            if (confirm('Are you sure you want to cancel the current sync?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route("hubspot.cancel") }}';
                
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_token';
                csrfInput.value = csrfToken;
                
                form.appendChild(csrfInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Start polling if sync is running
        if (document.getElementById('sync-status').textContent.trim().toLowerCase() === 'running') {
            pollingInterval = setInterval(updateStatus, 5000); // Poll every 5 seconds
        }
        
        // Setup cancel button event
        const cancelBtn = document.getElementById('cancel-sync-btn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', cancelSync);
        }
    </script>
</body>
</html>