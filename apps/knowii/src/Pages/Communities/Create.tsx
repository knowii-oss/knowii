import AppLayout from '@/Layouts/AppLayout';

export default function CreateCommunity() {
  return (
    <AppLayout title="New Community" header={<h1 className="text-xl font-semibold leading-tight text-white">New Community</h1>}>
      <div className="flex flex-col w-full items-center">Yay, let's create a new community</div>
    </AppLayout>
  );
}
