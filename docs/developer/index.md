# Hardenly developer docs

Hardenly is a read-only security scanner for WordPress. Other plugins can extend it through a few
public hooks: add their own checks, add vulnerability databases, change how the score is weighted and
react when a scan finishes.

- [Extending Hardenly](extending.md): examples for each extension point.
- [Hooks](hooks.md): reference for every action and filter, generated from the source.

The hooks and the types they use are public API, and changes to them are listed in the changelog:
`Check`, `Context`, `Group` and `Result` in `Hardenly\Scanner`, and `VulnerabilityProvider`,
`Component`, `Vulnerability` and `ProviderException` in `Hardenly\Vulnerability`. Other classes are internal and may change in any release.

Source code and issues: [github.com/creogen/hardenly](https://github.com/creogen/hardenly).
