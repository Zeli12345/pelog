using BalilogKiosk.App.Services;
using BalilogKiosk.Core.Api;
using BalilogKiosk.Core.Config;
using BalilogKiosk.Core.Data;
using BalilogKiosk.Core.Security;
using BalilogKiosk.Core.Services;
using BalilogKiosk.Core.Time;

namespace BalilogKiosk.App;

/// <summary>Hasil pemeriksaan token perangkat ke server.</summary>
public enum DeviceTokenStatus
{
    /// <summary>Token diterima server. Gangguan jaringan juga dianggap valid agar kiosk tetap jalan dengan data lokal.</summary>
    Valid,

    /// <summary>Token ditolak (device_token_invalid): token lokal dihapus, perlu pendaftaran ulang.</summary>
    Invalid,

    /// <summary>Perangkat dihapus dari dashboard (410 device_revoked): kiosk harus wipe total.</summary>
    Revoked,
}

/// <summary>
/// Kumpulan layanan aplikasi kiosk (DI sederhana).
/// </summary>
public sealed class AppServices : IDisposable
{
    public KioskConfig Config { get; }

    public ServerClock Clock { get; }

    public HttpClient Http { get; }

    public BalilogApiClient Api { get; }

    public LocalStore Store { get; }

    public SessionManager Sessions { get; }

    public SyncService Sync { get; }

    public UpdateService Updates { get; }

    public string DataDirectory { get; }

    public string ScreenshotDirectory { get; }

    private AppServices(
        KioskConfig config,
        string dataDirectory,
        ServerClock clock,
        HttpClient http,
        BalilogApiClient api,
        LocalStore store,
        SessionManager sessions,
        SyncService sync,
        UpdateService updates)
    {
        Config = config;
        DataDirectory = dataDirectory;
        ScreenshotDirectory = Path.Combine(dataDirectory, "screenshots");
        Clock = clock;
        Http = http;
        Api = api;
        Store = store;
        Sessions = sessions;
        Sync = sync;
        Updates = updates;
    }

    public static AppServices Create()
    {
        var configDirectory = ResolveWritableDirectory("BALI-LOG");
        var dataDirectory = Path.Combine(configDirectory, "data");

        Directory.CreateDirectory(dataDirectory);

        var configPath = Path.Combine(configDirectory, "balilog.json");
        var config = KioskConfig.Load(configPath);

        // Kode enrollment dari installer (jika ada) menjadi nilai awal.
        var enrollmentFile = Path.Combine(configDirectory, "enrollment.txt");

        if (string.IsNullOrEmpty(config.EnrollmentCode) && File.Exists(enrollmentFile))
        {
            config.EnrollmentCode = File.ReadAllText(enrollmentFile).Trim();
        }

        var clock = new ServerClock();
        var http = new HttpClient();
        var api = new BalilogApiClient(http, clock, config.NormalizedBaseUrl());
        var store = new LocalStore(Path.Combine(dataDirectory, "local.db"));
        var sessions = new SessionManager(store, api, clock);
        var sync = new SyncService(store, api, clock);
        var updates = new UpdateService(store, api, dataDirectory);

        return new AppServices(config, dataDirectory, clock, http, api, store, sessions, sync, updates);
    }

    /// <summary>Menyimpan/memuat token perangkat dengan DPAPI.</summary>
    public void SaveDeviceToken(string token)
    {
        Store.SetKv("device_token", TokenProtector.Protect(token));
        Api.DeviceToken = token;
    }

    public bool TryLoadDeviceToken()
    {
        var stored = Store.GetKv("device_token");

        if (string.IsNullOrEmpty(stored))
        {
            return false;
        }

        var token = TokenProtector.Unprotect(stored);

        if (string.IsNullOrEmpty(token))
        {
            return false;
        }

        Api.DeviceToken = token;

        return true;
    }

    /// <summary>
    /// Memeriksa token perangkat ke server lewat panggilan ringan terautentikasi.
    /// Bila server menolak token (device_token_invalid), token lokal dihapus agar
    /// perangkat dapat didaftarkan ulang. Bila perangkat DIHAPUS dari dashboard
    /// (device_revoked), hasil <see cref="DeviceTokenStatus.Revoked"/> dikembalikan
    /// tanpa tindakan — pemanggil yang memutuskan wipe total. Gangguan jaringan
    /// tidak mengubah apa pun — kiosk tetap berjalan dengan data lokal.
    /// </summary>
    public async Task<DeviceTokenStatus> VerifyDeviceTokenAsync(CancellationToken cancellationToken = default)
    {
        if (string.IsNullOrEmpty(Api.DeviceToken))
        {
            return DeviceTokenStatus.Invalid;
        }

        var result = await Api.GetLatestAppAsync(cancellationToken);

        if (SelfWipeService.IsRevoked(result))
        {
            return DeviceTokenStatus.Revoked;
        }

        if (result.ErrorCode == "device_token_invalid")
        {
            Store.SetKv("device_token", string.Empty);
            Api.DeviceToken = null;

            LocalLog.Write(
                DataDirectory,
                "token perangkat ditolak server (device_token_invalid) — token dihapus, perlu pendaftaran ulang.");

            return DeviceTokenStatus.Invalid;
        }

        return DeviceTokenStatus.Valid;
    }

    public string GetOrCreateDeviceUuid()
    {
        var existing = Store.GetKv("device_uuid");

        if (!string.IsNullOrEmpty(existing))
        {
            return existing;
        }

        var uuid = Guid.NewGuid().ToString();
        Store.SetKv("device_uuid", uuid);

        return uuid;
    }

    private static string ResolveWritableDirectory(string appName)
    {
        var candidates = new[]
        {
            Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData), appName),
            Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), appName),
        };

        foreach (var candidate in candidates)
        {
            try
            {
                Directory.CreateDirectory(candidate);

                var probe = Path.Combine(candidate, ".write-test");
                File.WriteAllText(probe, "ok");
                File.Delete(probe);

                return candidate;
            }
            catch (Exception)
            {
                // coba kandidat berikutnya
            }
        }

        var fallback = Path.Combine(Path.GetTempPath(), appName);
        Directory.CreateDirectory(fallback);

        return fallback;
    }

    public void Dispose()
    {
        Store.Dispose();
        Http.Dispose();
    }
}
