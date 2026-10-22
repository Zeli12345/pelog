using System.Net.Http;
using PelogKiosk.Core.Api;
using PelogKiosk.Core.Services;
using PelogKiosk.Core.Time;

namespace PelogKiosk.Tests;

public sealed class UpdateGateTests
{
    [Fact]
    public async Task State_staged_memblokir_login_sebelum_versi_terpasang()
    {
        var directory = Path.Combine(Path.GetTempPath(), "pelog-update-gate-" + Guid.NewGuid().ToString("N"));
        var dataDirectory = Path.Combine(directory, "data");
        var updates = Path.Combine(directory, "updates");
        Directory.CreateDirectory(updates);
        File.WriteAllText(Path.Combine(updates, "state.json"), """
            {"state":"staged","target_version":"99.0.0","attempts":0}
            """);

        try
        {
            var api = new PelogApiClient(new HttpClient(), new ServerClock(), "https://example.invalid/api/v1");
            using var cancelled = new CancellationTokenSource();
            cancelled.Cancel();

            var decision = await UpdateGate.EvaluateAsync(api, dataDirectory, cancelled.Token);

            Assert.True(decision.Block);
            Assert.Equal("99.0.0", decision.TargetVersion);
            Assert.Equal("staged", decision.State);
        }
        finally
        {
            Directory.Delete(directory, recursive: true);
        }
    }

    [Fact]
    public async Task Pending_reboot_tetap_memblokir_walau_versi_file_sudah_sama()
    {
        var directory = Path.Combine(Path.GetTempPath(), "pelog-update-gate-" + Guid.NewGuid().ToString("N"));
        var dataDirectory = Path.Combine(directory, "data");
        var updates = Path.Combine(directory, "updates");
        Directory.CreateDirectory(updates);
        File.WriteAllText(Path.Combine(updates, "state.json"), """
            {"state":"installed_pending_reboot","target_version":"1.0.3","attempts":0}
            """);

        try
        {
            var api = new PelogApiClient(new HttpClient(), new ServerClock(), "https://example.invalid/api/v1");
            using var cancelled = new CancellationTokenSource();
            cancelled.Cancel();

            var decision = await UpdateGate.EvaluateAsync(api, dataDirectory, cancelled.Token);

            Assert.True(decision.Block);
            Assert.Equal("installed_pending_reboot", decision.State);
        }
        finally
        {
            Directory.Delete(directory, recursive: true);
        }
    }

    [Fact]
    public async Task Tidak_ada_state_dan_server_tidak_terjangkau_tidak_memblokir()
    {
        var directory = Path.Combine(Path.GetTempPath(), "pelog-update-gate-" + Guid.NewGuid().ToString("N"));
        var dataDirectory = Path.Combine(directory, "data");
        Directory.CreateDirectory(dataDirectory);

        try
        {
            var api = new PelogApiClient(new HttpClient(), new ServerClock(), "https://example.invalid/api/v1");
            using var timeout = new CancellationTokenSource(TimeSpan.FromMilliseconds(200));

            var decision = await UpdateGate.EvaluateAsync(api, dataDirectory, timeout.Token);

            Assert.False(decision.Block);
        }
        finally
        {
            Directory.Delete(directory, recursive: true);
        }
    }
}
