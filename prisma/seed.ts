import { PrismaClient } from '@prisma/client';
import { faker } from '@faker-js/faker';
import { from, of } from 'rxjs';
import { concatMap, tap } from 'rxjs/operators';
import { createId } from '@paralleldrive/cuid2';
import { NewUserDto, validUserRoles } from '../apps/knowii/lib/domain/user.intf';

const prisma = new PrismaClient();

const userCount = 100;

const userIds: string[] = [];
const users: NewUserDto[] = Array.from({ length: userCount }).map(() => {
  // @ts-ignore
  const id = createId();
  userIds.push(id);
  const givenName = faker.name.firstName();
  const familyName = faker.name.lastName();
  const username = givenName + '.' + familyName;
  const email = faker.internet.email(givenName, familyName);

  const retVal: NewUserDto = {
    id,
    givenName,
    familyName,
    email,
    userRole: faker.helpers.arrayElement(Object.values(validUserRoles)),
    name: username,
    image: null,
    // FIXME complete
  };

  return retVal;
});

function main() {
  return of(void 0).pipe(
    concatMap(() => prisma.user.deleteMany()),
    concatMap(() =>
      from(
        prisma.user.createMany({
          data: users,
        })
      )
    ),
    tap((created) => {
      console.log('Users added: ', created);
    })
  );
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
