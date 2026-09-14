using BalilogKiosk.Core.Security;
using Xunit;

namespace BalilogKiosk.Tests;

public class PinHasherTests
{
    /// <summary>
    /// Vektor dihasilkan oleh PHP (server) memakai hash_pbkdf2('sha256', '2468', $salt, 100000, 32, true).
    /// Test ini memastikan C# dan PHP menghasilkan hash yang identik.
    /// </summary>
    [Fact]
    public void Verify_MatchesPhpGeneratedVector()
    {
        const string salt = "ASNFZ4mrze8BI0VniavN7w==";
        const string hash = "6kZ06H8QhrB5sp7U3GVttvbAULcRQqyOeecRKr80AH8=";

        Assert.True(PinHasher.Verify("2468", salt, 100_000, hash));
        Assert.False(PinHasher.Verify("2469", salt, 100_000, hash));
        Assert.False(PinHasher.Verify("", salt, 100_000, hash));
    }

    [Fact]
    public void Make_And_Verify_Roundtrip()
    {
        var (algo, salt, iterations, hash) = PinHasher.Make("2468");

        Assert.Equal("pbkdf2-sha256", algo);
        Assert.Equal(100_000, iterations);
        Assert.True(PinHasher.Verify("2468", salt, iterations, hash));
        Assert.False(PinHasher.Verify("1357", salt, iterations, hash));
    }

    [Fact]
    public void Make_Uses_Random_Salt()
    {
        var first = PinHasher.Make("2468");
        var second = PinHasher.Make("2468");

        Assert.NotEqual(first.Salt, second.Salt);
        Assert.NotEqual(first.Hash, second.Hash);
    }

    [Theory]
    [InlineData("0000")]
    [InlineData("1111")]
    [InlineData("1234")]
    [InlineData("4321")]
    [InlineData("0123")]
    [InlineData("9876")]
    [InlineData("abcd")]
    [InlineData("")]
    public void Weak_Pins_Are_Detected(string pin)
    {
        Assert.True(PinPolicy.IsWeak(pin));
    }

    [Theory]
    [InlineData("2468")]
    [InlineData("1357")]
    [InlineData("9182")]
    [InlineData("5061")]
    public void Strong_Pins_Pass(string pin)
    {
        Assert.False(PinPolicy.IsWeak(pin));
    }
}
