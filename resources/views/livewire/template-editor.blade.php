<div
x-data="{
    component: null,
        addEventListenersToFormInputs(){
            let that = this;
            @foreach($this->data as $key => $data)
                $wire.$watch('data.{{ $key }}', function(value){

                    console.log('{{  $key }}: ', value);
                    that.component.render($wire.data);
                    console.log(that.component);
                });
            @endforeach
        }
    }"
    x-init="
    // on document load
    setTimeout(function(){
        component = document.getElementById('page').contentWindow.document.getElementById('component');

console.log('coolio');
console.log(component);
        addEventListenersToFormInputs();
        }, 1000);
    "
class="h-full flex flex-col">
    <div class="p-4 border-b border-gray-200 bg-white">
        <h2 class="text-lg font-semibold text-gray-900">Edit Template</h2>
        <p class="text-sm text-gray-500">{{ $template }}</p>
    </div>



    <form wire:submit="save" class="flex-1 flex flex-col overflow-hidden">
        <div class="flex-1 overflow-y-auto p-4 space-y-4">
            {{ $this->form }}
        </div>

        <div class="p-4 border-t border-gray-200 bg-gray-50">
            <x-katana.button type="submit" class="w-full">
                Save Changes
            </x-katana.button>
        </div>
    </form>
</div>
