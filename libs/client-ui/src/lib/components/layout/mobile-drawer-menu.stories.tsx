import type { ComponentStory, ComponentMeta } from '@storybook/react';
import { MobileDrawerMenu } from './mobile-drawer-menu';

const Story: ComponentMeta<typeof MobileDrawerMenu> = {
  component: MobileDrawerMenu,
  title: 'MobileDrawerMenu',
  argTypes: {
    onClose: { action: 'onClose executed!' },
  },
};
export default Story;

const Template: ComponentStory<typeof MobileDrawerMenu> = (args) => <MobileDrawerMenu {...args} />;

export const Primary = Template.bind({});
Primary.args = {
  isOpen: false,
};
