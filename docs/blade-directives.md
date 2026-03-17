# Blade Directives

`@can('admin.*')` works out of the box. Additional directives:

```blade
@canPermission('admin.*')          @endcanPermission
@canAnyPermission(['a.*', 'b.*'])  @endcanAnyPermission
@canAllPermissions(['a.*', 'b.*']) @endcanAllPermissions
@hasRoleOrPermission('admin|a.*')  @endhasRoleOrPermission
@unlessCanPermission('admin.*')    @endunlessCanPermission
```
