<link rel="stylesheet" href="{{ asset('css/registration-number-search.css') }}">

<div class="registration-search-page"
     data-results-url="{{ route('registration-search.results') }}"
     data-pdf-url="{{ route('registration-search.pdf') }}"
     data-xlsx-url="{{ route('registration-search.xlsx') }}"
     data-storage-url="{{ rtrim(config('app.api_url'), '/api') }}/storage"
     data-fallback-image="{{ asset('images/male.png') }}">
    <section class="registration-search-card">
        <div class="registration-search-heading">
            <span class="registration-search-icon"><i class="fa-solid fa-address-card"></i></span>
            <div>
                <h2>Search by Reg No</h2>
                <p>Paste multiple registration numbers, separated by new lines, commas or spaces.</p>
            </div>
        </div>

        <form id="registrationSearchForm" novalidate>
            @csrf
            <textarea id="registrationNumbers" name="reg_nos" rows="4" placeholder="M-1588/16&#10;F-73/437" aria-label="Registration numbers"></textarea>
            <div id="registrationSearchError" class="registration-search-error" role="alert"></div>
            <div class="registration-search-actions">
                <small>Duplicate numbers are automatically ignored. Maximum 1,000 numbers.</small>
                <button type="submit" class="registration-proceed-button">
                    <i class="fa-solid fa-magnifying-glass"></i> Proceed
                </button>
            </div>
        </form>
    </section>

    <section id="registrationResultsCard" class="registration-results-card" hidden>
        <div class="registration-results-header">
            <div>
                <h3>Search Results</h3>
                <p><span id="registrationResultCount">0</span> participants found</p>
            </div>
            <button type="button" id="openRegistrationDownload" class="registration-download-button" disabled>
                <i class="fa-solid fa-download"></i> Download
            </button>
        </div>

        <div id="registrationMissingNotice" class="registration-missing-notice" hidden></div>

        <div class="registration-results-scroll">
            <table class="registration-results-table">
                <thead>
                    <tr>
                        <th>Sl</th>
                        <th>Img</th>
                        <th>Age</th>
                        <th>Occupation</th>
                        <th>Status</th>
                        <th>Name</th>
                        <th>Branch</th>
                        <th>Reg No.</th>
                        <th>Mobile</th>
                    </tr>
                </thead>
                <tbody id="registrationResultsBody"></tbody>
            </table>
        </div>
    </section>
</div>

<div id="registrationDownloadModal" class="registration-download-modal" aria-hidden="true">
    <div class="registration-download-dialog" role="dialog" aria-modal="true" aria-labelledby="registrationDownloadTitle">
        <div class="registration-download-title-row">
            <h3 id="registrationDownloadTitle">Choose download format</h3>
            <button type="button" class="registration-download-close" aria-label="Close">&times;</button>
        </div>
        <div class="registration-download-options">
            <form class="registration-export-form" method="POST" target="_blank">
                @csrf
                <input type="hidden" name="reg_nos" value="">
                <button type="submit" class="registration-format-card registration-format-pdf">
                    <i class="fa-solid fa-file-pdf"></i>
                    <span>Download in PDF</span>
                </button>
            </form>
            <form class="registration-export-form" method="POST">
                @csrf
                <input type="hidden" name="reg_nos" value="">
                <button type="submit" class="registration-format-card registration-format-xlsx">
                    <i class="fa-solid fa-file-excel"></i>
                    <span>Download in XLSX</span>
                </button>
            </form>
        </div>
    </div>
</div>

<script src="{{ asset('js/ajax/registration-number-search.js') }}"></script>
