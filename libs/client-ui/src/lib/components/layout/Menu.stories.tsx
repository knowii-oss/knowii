import type { ComponentStory, ComponentMeta } from '@storybook/react';
import { Menu } from './Menu';

const Story: ComponentMeta<typeof Menu> = {
  component: Menu,
  title: 'Menu',
};
export default Story;

const Template: ComponentStory<typeof Menu> = (args) => <Menu {...args} />;

export const Primary = Template.bind({});
Primary.args = {};
