<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Two-Factor Verification</title>
</head>
<body>
    <h1>Two-Factor Verification</h1>
    @if($errors->any())
        <div style="color:red">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="{{ route('2fa.verify.post') }}">
        @csrf
        <label for="code">Authentication Code</label>
        <input id="code" name="code" type="text" required />
        <button type="submit">Verify</button>
    </form>
</body>
</html>