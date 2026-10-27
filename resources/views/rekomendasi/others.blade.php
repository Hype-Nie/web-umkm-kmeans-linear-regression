<section class="rec-other-section mt-4" aria-labelledby="other-recommendations-title">
    <div class="rec-other-heading"><h2 id="other-recommendations-title">Rekomendasi Lainnya</h2><span></span></div>
    <div class="rec-other-list">
        @forelse ($otherItems as $rec)
            <details class="rec-other-row">
                <summary>
                    <span class="badge badge-priority-low">RENDAH</span>
                    <span class="rec-other-copy"><strong>{{ $rec->judul }}</strong><span>{{ Str::limit(str_replace(';', ',', $rec->deskripsi), 110) }}</span></span>
                    <span class="rec-other-confidence">Keyakinan: {{ $rec->tingkat_keyakinan }}</span>
                    <span class="rec-other-chevron" aria-hidden="true">›</span>
                </summary>
                <div class="rec-other-body">
                    <p>{{ str_replace(';', ',', $rec->deskripsi) }}</p>
                    <form method="post" action="{{ route('rekomendasi.update', $rec) }}" class="actions">
                        @csrf @method('patch')
                        <label for="other-status-{{ $rec->id_rekomendasi }}">Status</label>
                        <select class="form-select" id="other-status-{{ $rec->id_rekomendasi }}" name="status">
                            @foreach (['baru' => 'Baru', 'ditinjau' => 'Ditinjau', 'selesai' => 'Selesai'] as $value => $label)
                                <option value="{{ $value }}" @selected($rec->status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-primary" type="submit">Simpan Status</button>
                    </form>
                </div>
            </details>
        @empty
            <p class="rec-other-empty">Belum ada rekomendasi prioritas rendah yang sesuai dengan filter.</p>
        @endforelse
    </div>
    {{ $otherItems->links() }}
</section>
