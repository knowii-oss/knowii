import AppLayout from '@/Layouts/AppLayout';

export default function Dashboard() {
  return (
    <AppLayout title="Dashboard" header={<h1 className="text-xl font-semibold leading-tight text-white">Dashboard</h1>}>
      <div className="flex flex-col justify-center">Welcome to your personal Dashboard</div>
    </AppLayout>
  );
}
