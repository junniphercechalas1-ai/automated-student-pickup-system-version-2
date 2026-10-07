@extends('staff.layout')

@section('title', 'Pickup Verification')

@section('head')
    @vite(['resources/js/staff-pickup.js'])
@endsection

@section('content')
                <section class="staff-portal-card staff-portal-card--scanner">
                    <div class="staff-portal-scanner-header">
                        <div>
                            <p class="staff-portal-kicker">QR Scanner</p>
                            <h3>Gate verification</h3>
                            <p class="staff-portal-subtitle">Scan the parent/guardian QR code to verify entry.</p>
                        </div>
                        <div class="staff-portal-actions">
                            <button id="startScan" type="button" class="staff-portal-primary-btn">Start scanner</button>
                            <button id="stopScan" type="button" class="staff-portal-secondary-btn">Stop scanner</button>
                        </div>
                    </div>

                    <div class="staff-portal-scan-layout">
                        <div class="staff-portal-scan-stage">
                            <div class="staff-portal-scan-frame">
                                <video id="scannerVideo" class="staff-portal-video" playsinline webkit-playsinline muted autoplay></video>
                                <div id="scanOverlay" class="staff-portal-scan-overlay" aria-live="polite">
                                    <div id="scanBox" class="staff-portal-scan-box" aria-label="QR scanning area">
                                        <span class="staff-portal-scan-corner top-left"></span>
                                        <span class="staff-portal-scan-corner top-right"></span>
                                        <span class="staff-portal-scan-corner bottom-left"></span>
                                        <span class="staff-portal-scan-corner bottom-right"></span>
                                        <span class="staff-portal-scan-line" aria-hidden="true"></span>
                                    </div>
                                    <span id="scanHud" class="staff-portal-scan-hud"></span>
                                </div>
                                <canvas id="scannerCanvas" class="hidden"></canvas>
                            </div>
                        </div>

                        <aside class="staff-portal-help-panel">
                            <h4>Scanner Status</h4>
                            <div style="background-color: #f5f5f5; padding: 12px; border-radius: 8px; margin-bottom: 12px;">
                                <p style="margin: 0; font-size: 14px;"><strong>Status:</strong></p>
                                <div class="staff-portal-status-line">
                                    <span id="verificationIndicator" class="staff-portal-verification-indicator neutral" aria-label="QR verification: neutral"></span>
                                    <p id="pickerStatus" class="staff-portal-picker-status" style="margin: 8px 0 0 0; font-size: 16px; font-weight: bold;">Scanner stopped.</p>
                                </div>
                            </div>
                            <div style="background-color: #f5f5f5; padding: 12px; border-radius: 8px; margin-bottom: 12px;">
                                <p style="margin: 0; font-size: 14px;"><strong>Last Scanned QR:</strong></p>
                                <p id="lastCode" class="staff-portal-last-code" style="margin: 8px 0 0 0; font-size: 13px; color: #666;" title="">None</p>
                            </div>
                            <h4>How to Scan</h4>
                            <ol>
                                <li>Ask the parent or guardian to show their QR code.</li>
                                <li>Position the QR code within the frame.</li>
                                <li>Wait for verification.</li>
                            </ol>
                            <div class="staff-portal-tip">Make sure the QR code is clearly visible.</div>
                        </aside>
                    </div>
                </section>

                <section class="staff-portal-card staff-portal-card--table">
                    <div class="staff-portal-table-header">
                        <div>
                            <p class="staff-portal-kicker">Recent Scans</p>
                            <h3>Latest verification results</h3>
                        </div>
                        <a href="/staff/pickup-records">View All</a>
                    </div>

                    <div class="staff-portal-table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Student</th>
                                    <th>Parent / Guardian</th>
                                    <th>Result</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td id="pickupTime" data-label="Time">-</td>
                                    <td data-label="Student">
                                        <div class="staff-portal-identity">
                                            <span class="staff-portal-avatar small" id="studentName">-</span>
                                            <div>
                                                <strong id="studentNameText">-</strong>
                                                <small id="studentId">-</small>
                                                <small id="studentClass" style="display: block; margin-top: 4px; color: #999;">-</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td id="guardianName" data-label="Parent / Guardian">-</td>
                                    <td data-label="Result"><span id="scanResult" class="staff-portal-status neutral">Ready</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="staff-portal-status-row">
                    <div class="staff-portal-status-box">
                        <div class="staff-portal-status-icon">✓</div>
                        <div>
                            <p class="staff-portal-kicker">System Status</p>
                            <h3>Scanner is connected and ready.</h3>
                        </div>
                    </div>
                    <div class="staff-portal-meta-box">
                        <span class="staff-portal-meta-label">Today</span>
                        <strong id="currentDate">-</strong>
                        <span class="staff-portal-meta-label">Time</span>
                        <strong id="currentTime">-</strong>
                    </div>
                </section>
            @endsection
