# TODO

- implement Footer
- Create a GuestLayout and use it for all auth pages (except Welcome)
  - Use GuestFooter in there
- Extract min content pane size to class, and reuse it: min-h-[48rem]
- implement Banner component
- remove usages of headlessui/react
- remove usages of useForm() without the type parameter. See UpdateProfileInformationForm.tsx for a good example
- add addLink to ApplicationLogo, use it on AuthenticationCard, and remove AuthenticationCardLogo

use Illuminate\Support\Facades\Log;
Log::emergency("LOL");
dd($response->getStatusCode());
