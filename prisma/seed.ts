import { PrismaClient } from '@prisma/client';
import { of } from 'rxjs';

const prisma = new PrismaClient();

function main() {
  return of(void 0);
}

main().subscribe({
  error: (err) => {
    console.log('Failed seeding! Error: ', JSON.stringify(err));
    process.exit(1);
  },
  complete: () => {
    console.log('Done seeding');
    prisma.$disconnect();
  },
});
