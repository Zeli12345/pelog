using System.Net;
using System.Text;
using System.Text.Json;
using PelogKiosk.Core.Api;
using PelogKiosk.Core.Data;
using PelogKiosk.Core.Services;
using PelogKiosk.Core.Time;
using Xunit;

namespace PelogKiosk.Tests;

public class UpdateStagingTests : IDisposable
{
    private readonly string _directory;

    private readonly string _dataDirectory;

    private readonly LocalStore _store;

    private readonly UpdateService _updates;

    public UpdateStagingTests()
    {
        _directory = Path.Combine(Path.GetTempPath(), "pelog-tests", Guid.NewGuid().ToString("N"));
        _dataDirectory = Path.Combine(_directory, "data");
        Directory.CreateDirectory(_dataDirectory);
        _store = new LocalStore(Path.Combine(_dataDirectory, "local.db"));

        var clock = new ServerClock();
        var api = new PelogApiClient(new HttpClient(), clock, "http://127.0.0.1:9");
        _updates = new UpdateService(_store, api, _dataDirectory);
    }

    public void Dispose()
    {
        _store.Dispose();

        try
        {
            Directory.Delete(_directory, recursive: true);
        }
        catch (IOException)
        {
            // abaikan sisa file terkunci
        }
    }

    [Fact]
    public void Cleanup_Removes_Stale_Staging_When_Not_Newer()
    {
        var installerPath = StageInstaller("0.9.0");
        _store.SetKv("update_staged_version", "0.9.0");

        _updates.CleanupStaleStaging();

        Assert.False(File.Exists(installerPath));
        Assert.False(File.Exists(Path.Combine(_directory, "update.json")));
        Assert.True(string.IsNullOrEmpty(_store.GetKv("update_staged_version")));
    }

    [Fact]
    public void Cleanup_Keeps_Pending_Newer_Staging()
    {
        var installerPath = StageInstaller("99.0.0");
        _store.SetKv("update_staged_version", "99.0.0");

        _updates.CleanupStaleStaging();

        Assert.True(File.Exists(installerPath));
        Assert.True(File.Exists(Path.Combine(_directory, "update.json")));
        Assert.Equal("99.0.0", _store.GetKv("update_staged_version"));
    }

    [Fact]
    public async Task Failed_Check_Does_Not_Persist_Last_Check()
    {
        var updates = CreateUpdates(new StubHandler(_ => throw new HttpRequestException("offline")));

        var result = await updates.CheckAndStageAsync(6);

        Assert.Equal(UpdateCheckOutcome.Failed, result.Outcome);
        Assert.True(string.IsNullOrEmpty(_store.GetKv("update_last_check")));
    }

    [Fact]
    public async Task Successful_Check_Persists_Last_Check()
    {
        var updates = CreateUpdates(new StubHandler(_ => Json(UpToDateJson("1.0.2"))));

        var result = await updates.CheckAndStageAsync(6);

        Assert.Equal(UpdateCheckOutcome.UpToDate, result.Outcome);
        Assert.False(string.IsNullOrEmpty(_store.GetKv("update_last_check")));
    }

    [Fact]
    public async Task Missing_Staging_Files_Trigger_Re_download()
    {
        _store.SetKv("update_staged_version", "99.0.0");

        var updates = CreateUpdates(new StubHandler(ReleaseOrMissingInstaller("99.0.0")));

        var result = await updates.CheckAndStageAsync(6);

        // Manifest/installer tidak ada: staging dianggap basi dan diunduh ulang
        // (di sini unduhan gagal karena handler mengembalikan 404), bukan "Staged".
        Assert.Equal(UpdateCheckOutcome.Failed, result.Outcome);
        Assert.True(string.IsNullOrEmpty(_store.GetKv("update_staged_version")));
        Assert.False(File.Exists(Path.Combine(_directory, "update.json")));
    }

    [Fact]
    public async Task Corrupted_Staged_Installer_Is_Re_downloaded()
    {
        var installerPath = StageInstaller("99.0.0");
        File.WriteAllText(installerPath, "berkas berubah setelah staging");
        _store.SetKv("update_staged_version", "99.0.0");

        var updates = CreateUpdates(new StubHandler(ReleaseOrMissingInstaller("99.0.0")));

        var result = await updates.CheckAndStageAsync(6);

        Assert.Equal(UpdateCheckOutcome.Failed, result.Outcome);
        Assert.True(string.IsNullOrEmpty(_store.GetKv("update_staged_version")));
        Assert.False(File.Exists(Path.Combine(_directory, "update.json")));
        Assert.False(File.Exists(installerPath));
    }

    [Fact]
    public async Task Intact_Staging_Is_Reused_Without_Download()
    {
        var installerPath = StageInstaller("99.0.0");
        _store.SetKv("update_staged_version", "99.0.0");

        var handler = new StubHandler(_ => Json(ReleaseJson("99.0.0", "tidak-dipakai")));
        var updates = CreateUpdates(handler);

        var result = await updates.CheckAndStageAsync(6);

        Assert.Equal(UpdateCheckOutcome.Staged, result.Outcome);
        Assert.Equal("99.0.0", _store.GetKv("update_staged_version"));
        Assert.True(File.Exists(installerPath));
        Assert.Single(handler.Requests);
    }

    private UpdateService CreateUpdates(HttpMessageHandler handler) =>
        new(_store, new PelogApiClient(new HttpClient(handler), new ServerClock(), "http://pelog.test"), _dataDirectory);

    private static Func<HttpRequestMessage, HttpResponseMessage> ReleaseOrMissingInstaller(string version) =>
        request => request.RequestUri!.AbsolutePath.EndsWith("app/installer", StringComparison.Ordinal)
            ? new HttpResponseMessage(HttpStatusCode.NotFound)
            : Json(ReleaseJson(version, "abc"));

    private static HttpResponseMessage Json(string body) =>
        new(HttpStatusCode.OK)
        {
            Content = new StringContent(body, Encoding.UTF8, "application/json"),
        };

    private static string UpToDateJson(string version) =>
        JsonSerializer.Serialize(new { ok = true, data = new { available = false, version } });

    private static string ReleaseJson(string version, string sha256) =>
        JsonSerializer.Serialize(new
        {
            ok = true,
            data = new
            {
                available = true,
                version,
                sha256,
                url = "app/installer",
            },
        });

    private string StageInstaller(string version)
    {
        var updatesDirectory = Path.Combine(_dataDirectory, "updates");
        Directory.CreateDirectory(updatesDirectory);

        var installerPath = Path.Combine(updatesDirectory, $"PELOG_Setup_{version}.exe");
        File.WriteAllText(installerPath, "dummy installer");

        var manifest = JsonSerializer.Serialize(new
        {
            version,
            installer_path = installerPath,
            sha256 = UpdateService.ComputeSha256(installerPath),
        });

        File.WriteAllText(Path.Combine(_directory, "update.json"), manifest);

        return installerPath;
    }

    private sealed class StubHandler : HttpMessageHandler
    {
        private readonly Func<HttpRequestMessage, HttpResponseMessage> _respond;

        public StubHandler(Func<HttpRequestMessage, HttpResponseMessage> respond) => _respond = respond;

        public List<string> Requests { get; } = [];

        protected override Task<HttpResponseMessage> SendAsync(HttpRequestMessage request, CancellationToken cancellationToken)
        {
            Requests.Add(request.RequestUri?.AbsolutePath ?? string.Empty);

            return Task.FromResult(_respond(request));
        }
    }
}
