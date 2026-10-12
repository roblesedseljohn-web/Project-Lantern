<style>
    body {
        background: #0f172a;
        color: white;
        font-family: Arial, sans-serif;
        margin: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
    }

    .card {
        background: #1e293b;
        padding: 40px;
        border-radius: 20px;
        text-align: center;
        width: 350px;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
    }

    h1 {
        margin-bottom: 10px;
    }

    p {
        color: #cbd5e1;
        line-height: 1.6;
    }

    .description {
        margin-top: 15px;
    }

    .status {
        margin-top: 20px;
        padding: 10px;
        background: #16a34a;
        border-radius: 10px;
        font-weight: 600;
    }

    .btn {
        display: inline-block;
        margin-top: 20px;
        padding: 12px 28px;
        background: #2563eb;
        color: white;
        text-decoration: none;
        border-radius: 10px;
        font-weight: 600;
        transition: background 0.2s;
    }

    .btn:hover {
        background: #1d4ed8;
    }
</style>

<div class="card">
    <h1>Project Lantern</h1>

    <p class="description">
        Project Lantern is a self-hosted cloud storage platform designed
        to provide a simple, secure, and convenient way to store,
        organize, manage, and access files.
    </p>

    <p>
        Upload your files, organize them into folders, and access your
        data through a centralized storage system.
    </p>

    <div class="status">
        System Status: ONLINE
    </div>

    <a href="auth.php" class="btn">Go to Login</a>
</div>