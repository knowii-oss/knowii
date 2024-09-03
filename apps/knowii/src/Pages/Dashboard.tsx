import React from 'react';
import AppLayout from '@/Layouts/AppLayout';

export default function Dashboard() {
  return (
    <AppLayout title="Dashboard" header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Dashboard</h2>}>
      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div className="bg-white overflow-hidden shadow-xl sm:rounded-lg">Welcome to your personal Dashboard</div>
        </div>
      </div>
    </AppLayout>
  );
}
