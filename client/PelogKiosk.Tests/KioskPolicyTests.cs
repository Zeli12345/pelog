using Microsoft.Win32;
using PelogKiosk.Core.Services;

namespace Tests;

/// <summary>
/// Regresi bug fatal "laptop terkunci setelah shutdown saat tahap pendaftaran":
/// kiosk harus mampu melepaskan hardening pada HKCU. Karena sebagian key
/// Policies dilindungi ACL (bisa ditolak untuk proses non-elevated), API
/// membersihkan mengembalikan status dan wajib idempoten; penelepon wajib
/// menjalankan fallback elevated bila hasilnya false.
/// </summary>
public class KioskPolicyTests : IDisposable
{
    private readonly string _sandboxRoot;

    private readonly string _systemPath;
    private readonly string _explorerPath;
    private readonly string _cmdPath;

    public KioskPolicyTests()
    {
        // Sandbox nyata di HKCU — pola key disamakan dengan Policies, tapi
        // sepenuhnya milik test sehingga bebas ACL / bisa ditulis.
        _sandboxRoot = @"Software\PelogTest_" + Guid.NewGuid().ToString("N");
        _systemPath = _sandboxRoot + @"\Software\Microsoft\Windows\CurrentVersion\Policies\System";
        _explorerPath = _sandboxRoot + @"\Software\Microsoft\Windows\CurrentVersion\Policies\Explorer";
        _cmdPath = _sandboxRoot + @"\Software\Policies\Microsoft\Windows\System";
    }

    public void Dispose()
    {
        try
        {
            Registry.CurrentUser.DeleteSubKeyTree(_sandboxRoot, throwOnMissingSubKey: false);
        }
        catch (Exception)
        {
            // bersih-bersih best effort
        }
    }

    private void SeedKioskPolicies()
    {
        using (var systemKey = Registry.CurrentUser.CreateSubKey(_systemPath, true))
        {
            systemKey.SetValue("DisableTaskMgr", 1, RegistryValueKind.DWord);
            systemKey.SetValue("DisableRegistryTools", 1, RegistryValueKind.DWord);
        }

        using (var explorerKey = Registry.CurrentUser.CreateSubKey(_explorerPath, true))
        {
            explorerKey.SetValue("NoRun", 1, RegistryValueKind.DWord);
            explorerKey.SetValue("NoControlPanel", 1, RegistryValueKind.DWord);
            explorerKey.SetValue("DisallowRun", 1, RegistryValueKind.DWord);
        }

        using (var disallow = Registry.CurrentUser.CreateSubKey(_explorerPath + @"\DisallowRun", true))
        {
            disallow.SetValue("1", "cmd.exe", RegistryValueKind.String);
            disallow.SetValue("2", "powershell.exe", RegistryValueKind.String);
        }

        using (var cmdKey = Registry.CurrentUser.CreateSubKey(_cmdPath, true))
        {
            cmdKey.SetValue("DisableCMD", 1, RegistryValueKind.DWord);
        }
    }

    [Fact]
    public void Remove_menghapus_semua_kebijakan_dan_DisallowRun()
    {
        SeedKioskPolicies();

        Assert.True(KioskPolicy.AnyPolicyPresent(
            Registry.CurrentUser, _systemPath, _explorerPath, _cmdPath));

        var clean = KioskPolicy.Remove(
            Registry.CurrentUser, _systemPath, _explorerPath, _cmdPath);

        Assert.True(clean);
        Assert.False(KioskPolicy.AnyPolicyPresent(
            Registry.CurrentUser, _systemPath, _explorerPath, _cmdPath));

        using var explorerAfter = Registry.CurrentUser.OpenSubKey(_explorerPath);
        Assert.Null(explorerAfter?.GetValue("DisallowRun"));
        Assert.Null(explorerAfter?.OpenSubKey("DisallowRun"));
    }

    [Fact]
    public void Remove_idempoten_dan_tidak_melempar_saat_key_tidak_ada()
    {
        var clean = KioskPolicy.Remove(
            Registry.CurrentUser, _systemPath, _explorerPath, _cmdPath);

        Assert.True(clean);

        // Panggilan berulang tetap bersih tanpa kejadian.
        Assert.True(KioskPolicy.Remove(
            Registry.CurrentUser, _systemPath, _explorerPath, _cmdPath));
    }

    [Fact]
    public void PolicyValues_mencakup_seluruh_nilai_yang_ditulis_hardening_ps1()
    {
        var names = KioskPolicy.PolicyValues.Select(p => p.ValueName).ToHashSet();

        Assert.Equal(new HashSet<string>
        {
            "DisableTaskMgr", "DisableRegistryTools", "NoRun", "NoControlPanel",
            "DisableCMD", "DisallowRun",
        }, names);
    }
}
