# Livewire

```php
use NyonCode\PermissionExtended\Livewire\WithPermissions;

class AdminPanel extends Component
{
    use WithPermissions;

    public function mount() { $this->permAuthorize('admin.*'); }
}
```

| Method | Returns |
|--------|---------|
| `permAuthorize('p')` | void (abort 403) |
| `permAuthorizeAny([...])` | void |
| `permAuthorizeAll([...])` | void |
| `permAuthorizeRoleOr('r\|p')` | void |
| `permCan('p')` | bool |
| `permCanAny([...])` | bool |
| `permCanAll([...])` | bool |
| `permCanRoleOr('r\|p')` | bool |
| `permMatching('p.*')` | string[] |
| `permissionsChanged()` | bool |

```blade
@if($this->permCan('posts.*'))
    <button wire:click="create">New</button>
@endif
```
