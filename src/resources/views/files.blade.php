<form action="{{ route('files') }}" method="post" enctype="multipart/form-data">
    @csrf
    <div class="mb-3">
        <label for="file" class="form-label">انتخاب فایل:</label>
        <input type="file" name="file" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-success">آپلود</button>
</form>
